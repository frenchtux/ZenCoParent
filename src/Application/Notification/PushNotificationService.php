<?php
declare(strict_types=1);

namespace ZenCoParent\Application\Notification;

use Psr\Log\LoggerInterface;
use ZenCoParent\Domain\Messaging\ThreadRepositoryInterface;
use ZenCoParent\Domain\Notification\PushSenderInterface;
use ZenCoParent\Domain\User\UserRepositoryInterface;

/** Decides who gets notified about what. Never throws into the calling request. */
final class PushNotificationService
{
    private const BODY_MAX_LENGTH = 140;

    public function __construct(
        private readonly PushSenderInterface       $sender,
        private readonly ThreadRepositoryInterface $threads,
        private readonly UserRepositoryInterface   $users,
        private readonly LoggerInterface           $logger,
    ) {}

    public function messageSent(string $threadId, string $senderId, string $content): void
    {
        try {
            $recipients = array_values(array_filter(
                $this->threads->getParticipantIds($threadId),
                fn(string $id) => $id !== $senderId,
            ));
            if ($recipients === []) {
                return;
            }

            $sender  = $this->users->findById($senderId);
            $subject = $this->threads->findById($threadId)?->getSubject();
            $title   = 'Message de ' . ($sender?->getFirstName() ?: 'votre co-parent');
            if ($subject !== null && $subject !== '') {
                $title .= ' · ' . $subject;
            }

            $this->sender->send($recipients, [
                'title' => $title,
                'body'  => $this->excerpt($content),
                'url'   => '/frontend/messagerie.html?thread=' . rawurlencode($threadId),
                'tag'   => 'thread-' . $threadId,
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Push: messageSent failed', ['error' => $e->getMessage()]);
        }
    }

    public function eventCreated(string $tenantId, string $creatorId, string $title, string $startAt, bool $allDay): void
    {
        try {
            $recipients = [];
            foreach ($this->users->findByTenantId($tenantId) as $user) {
                if ($user->getId() !== $creatorId && $user->isActive()) {
                    $recipients[] = $user->getId();
                }
            }
            if ($recipients === []) {
                return;
            }

            $this->sender->send($recipients, [
                'title' => 'Nouvel événement : ' . $title,
                'body'  => $this->formatDate($startAt, $allDay),
                'url'   => '/frontend/calendrier.html',
                'tag'   => 'events',
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Push: eventCreated failed', ['error' => $e->getMessage()]);
        }
    }

    public function test(string $userId): void
    {
        $this->sender->send([$userId], [
            'title' => 'ZenCoParent',
            'body'  => 'Les notifications fonctionnent sur cet appareil.',
            'url'   => '/frontend/mon-profil.html',
            'tag'   => 'test',
        ]);
    }

    private function excerpt(string $text): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
        return mb_strlen($text) > self::BODY_MAX_LENGTH
            ? rtrim(mb_substr($text, 0, self::BODY_MAX_LENGTH - 1)) . '…'
            : $text;
    }

    private function formatDate(string $startAt, bool $allDay): string
    {
        try {
            $date = new \DateTimeImmutable($startAt);
        } catch (\Exception) {
            return '';
        }
        return $allDay
            ? 'Le ' . $date->format('d/m/Y')
            : 'Le ' . $date->format('d/m/Y') . ' à ' . $date->format('H:i');
    }
}
