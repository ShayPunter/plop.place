<?php

namespace App\Services;

use App\Models\AnonymousSession;
use App\Models\User;
use Illuminate\Support\Facades\Redis;

class CooldownService
{
    public const COOLDOWN_SECONDS = 5; // 5 second cooldown
    public const USER_COOLDOWN_PREFIX = 'cooldown:user:';
    public const SESSION_COOLDOWN_PREFIX = 'cooldown:session:';

    public function getCooldownKey(User|AnonymousSession $entity): string
    {
        if ($entity instanceof User) {
            return self::USER_COOLDOWN_PREFIX . $entity->id;
        }

        return self::SESSION_COOLDOWN_PREFIX . $entity->token;
    }

    public function isOnCooldown(User|AnonymousSession $entity): bool
    {
        $key = $this->getCooldownKey($entity);
        return Redis::exists($key);
    }

    public function getRemainingCooldown(User|AnonymousSession $entity): int
    {
        $key = $this->getCooldownKey($entity);
        $ttl = Redis::ttl($key);

        return max(0, $ttl);
    }

    public function getCooldownEndTime(User|AnonymousSession $entity): ?int
    {
        $remaining = $this->getRemainingCooldown($entity);

        if ($remaining <= 0) {
            return null;
        }

        return time() + $remaining;
    }

    public function startCooldown(User|AnonymousSession $entity): void
    {
        $key = $this->getCooldownKey($entity);
        Redis::setex($key, self::COOLDOWN_SECONDS, time());
    }

    public function clearCooldown(User|AnonymousSession $entity): void
    {
        $key = $this->getCooldownKey($entity);
        Redis::del($key);
    }

    public function canPlacePixel(User|AnonymousSession $entity): bool
    {
        return !$this->isOnCooldown($entity);
    }

    public static function getCooldownDuration(): int
    {
        return self::COOLDOWN_SECONDS;
    }
}
