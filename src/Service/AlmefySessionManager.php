<?php

namespace Almefy\AuthenticationBundle\Service;

use Almefy\Client;
use Almefy\Session;
use App\Service\CacheManager;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\CacheItem;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Contracts\Cache\CacheInterface;

class AlmefySessionManager
{
    public const CACHE_KEY_SESSION_LIST = 'ALMEFY_SESSION_LIST';
    public const CACHE_KEY_ACTIVE_SESSION_LIST = 'ALMEFY_ACTIVE_SESSION_LIST';
    public const CACHE_KEY_PATTERN_USER_SESSION = 'ALMEFY_SESSION_%s';

    public function __construct(
        private Client                 $client,
        private CacheInterface         $cache,
        private CacheItemPoolInterface $cacheAdapter,
        private int                    $sessionTTL = 360,
        private int                    $sessionCacheTTL = 15,
    ) {
    }


    public function addSession(Session $session): array
    {
        return $this->refreshActiveSessionList($this->updateSessionList($session));
    }

    public function refreshActiveSessionList(array $sessions): array
    {
        $timestamp = static fn (Session $session) => is_int($session->getExpiresAt()) ? $session->getExpiresAt() : (new \DateTime($session->getExpiresAt()))->getTimestamp();
        $now = new \DateTime();
        $list = $this->cacheAdapter->getItem(self::CACHE_KEY_ACTIVE_SESSION_LIST);
        $list->expiresAfter($this->sessionTTL * $this->sessionCacheTTL);
        $activeSessions = static::combineItems($sessions, $list->isHit() ? $list->get() : []);
        $filteredItems = array_filter($activeSessions, static fn(Session $session) => $timestamp($session) > $now->getTimestamp());
        $list->set($filteredItems);
        $this->cacheAdapter->save($list);

        return $list->get();
    }

    public function refreshSessionByIdentifier(string $sessionId, string $identifier): ?Session
    {
        // current session update
        $activeSessionsList = $this->updateSessionCache($sessionId, $identifier);
        // active session list update
        $this->updateActiveSessionList($activeSessionsList, $sessionId);

        // current session list
        $activeSessions = $this->refreshActiveSessionList($this->getSessions());

        return $activeSessions[$sessionId] ?? throw new AccessDeniedException();
    }

    public function getSessions()
    {
        $activeSessions = $this->cache->get(self::CACHE_KEY_ACTIVE_SESSION_LIST, static fn () => []);
        $sessionList = $this->cache->get(self::CACHE_KEY_SESSION_LIST, function (CacheItem $item) use ($activeSessions) {
            $this->removeExpiredSessions($activeSessions);
            $sessions = $this->client->getSessions($activeSessions);
            $now = new \DateTime();
            $filteredItems = array_filter($sessions, static fn(Session $session) => (new \DateTime($session->getExpiresAt()))->getTimestamp() > $now->getTimestamp());
            $item->expiresAfter($this->sessionCacheTTL);

            return $filteredItems;
        });

        return static::combineItems($sessionList, $activeSessions);
    }

    private function updateSessionList(Session $session): array
    {
        $sessionsItem = $this->cacheAdapter->getItem(self::CACHE_KEY_SESSION_LIST);
        $sessions = [$session->getId() => $session];
        if ($sessionsItem->isHit()) {
            $sessions = $sessionsItem->get();
            $sessions[$session->getId()] ??= $session;
        }
        $sessionsItem->set($sessions);
        $sessionsItem->expiresAfter($this->sessionCacheTTL);
        $this->cacheAdapter->save($sessionsItem);

        return $sessions;
    }


    private function updateSessionCache(string $sessionId, string $identifier): array
    {
        $activeSessionsList = [];
        $sessionItem = $this->cacheAdapter->getItem(sprintf(self::CACHE_KEY_PATTERN_USER_SESSION, $sessionId));
        if ($sessionItem->isHit()) {
            /** @var Session $session */
            $session = $sessionItem->get();
            if ($session instanceof Session && $session->getIdentifier() === $identifier) {
                $activeSessionsList = [$session->getId() => $session->withUpdateAt()];
                $this->cacheAdapter->save($sessionItem->set($session));
                $sessionItem->expiresAt(new \DateTime($session->getExpiresAt()));
            }
        }

        return $activeSessionsList;
    }

    public function updateActiveSessionList(array $activeSessionsList, string $sessionId): void
    {
        $session = $activeSessionsList[$sessionId] ?? false;
        $activeSessionsItem = $this->cacheAdapter->getItem(self::CACHE_KEY_ACTIVE_SESSION_LIST);
        if ($activeSessionsItem->isHit()) {
            $activeSessionsList = $activeSessionsItem->get();
            $currentSession = $activeSessionsList[$sessionId] ?? false;
            if ($currentSession instanceof Session && $session instanceof Session) {
                $activeSessionsList[$sessionId] = $session->withUpdateAt();
            }
        }
        $activeSessionsItem->set($activeSessionsList);
        $this->cacheAdapter->saveDeferred($activeSessionsItem);
    }

    public static function getSessionCacheKey(Session $session): string
    {
        return sprintf(self::CACHE_KEY_PATTERN_USER_SESSION, $session->getId());
    }

    public static function combineItems(array $main, array $active = []): array
    {
        foreach ($active as $id => $item) {
            $mainItem = $main[$id] ?? false;
            if ($mainItem instanceof Session) {
                $item->withUpdatedExpiration($item->getUpdatedAt(), $mainItem->getExpiresAt() ?? $item->getExpiresAt());
            }
            $main[$id] = $item;
        }

        return $main;
    }

    private function removeExpiredSessions(array $activeSessions): void
    {
        foreach ($activeSessions as $session) {
            if ($activeSessions[$session->getId()] ?? false) {
                // refresh active session? $activeSessions[$session->getId()]
                $activeSession = $this->cache->get(self::getSessionCacheKey($session), static fn() => $session);
                $expiresAt = new \DateTime($activeSession->getExpiresAt());
                $updatedAt = new \DateTime($activeSession->getUpdatedAt());
                $time = time();
                if ($expiresAt->getTimeStamp() < $time || $updatedAt->getTimestamp() + $this->sessionTTL < $time) {
                    $this->cache->delete(self::getSessionCacheKey($activeSessions[$session->getId()]));
                }
            }
        }
    }
}
