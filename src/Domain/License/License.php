<?php
declare(strict_types=1);

namespace ZenCoParent\Domain\License;

final class License
{
    private const TRIAL_DAYS = 30;

    public function __construct(
        private readonly string               $id,
        private readonly string               $installationKey,
        private readonly ?string              $activationKey,
        private readonly \DateTimeImmutable   $installedAt,
        private readonly ?\DateTimeImmutable  $activatedAt,
        private readonly bool                 $isActive,
        private readonly string               $instanceId,
        private readonly ?\DateTimeImmutable  $revokedAt          = null,
        private readonly ?string              $machineFingerprint = null,
    ) {}

    // ── Factory ──────────────────────────────────────────────────────────────

    public static function create(string $installationKey, string $instanceId): self
    {
        return new self(
            id:              \Ramsey\Uuid\Uuid::uuid4()->toString(),
            installationKey: $installationKey,
            activationKey:   null,
            installedAt:     new \DateTimeImmutable(),
            activatedAt:     null,
            isActive:        false,
            instanceId:      $instanceId,
        );
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id:                 $data['id'],
            installationKey:    $data['installation_key'],
            activationKey:      $data['activation_key'] ?? null,
            installedAt:        new \DateTimeImmutable($data['installed_at']),
            activatedAt:        isset($data['activated_at']) && $data['activated_at']
                                    ? new \DateTimeImmutable($data['activated_at'])
                                    : null,
            isActive:           (bool) $data['is_active'],
            instanceId:         $data['instance_id'] ?? '',
            revokedAt:          isset($data['revoked_at']) && $data['revoked_at']
                                    ? new \DateTimeImmutable($data['revoked_at'])
                                    : null,
            machineFingerprint: $data['machine_fingerprint'] ?? null,
        );
    }

    public function withActivation(string $activationKey, ?string $machineFingerprint = null): self
    {
        return new self(
            id:                 $this->id,
            installationKey:    $this->installationKey,
            activationKey:      $activationKey,
            installedAt:        $this->installedAt,
            activatedAt:        new \DateTimeImmutable(),
            isActive:           true,
            instanceId:         $this->instanceId,
            revokedAt:          $this->revokedAt,
            machineFingerprint: $machineFingerprint ?? $this->machineFingerprint,
        );
    }

    public function withRevocation(): self
    {
        return new self(
            id:                 $this->id,
            installationKey:    $this->installationKey,
            activationKey:      $this->activationKey,
            installedAt:        $this->installedAt,
            activatedAt:        $this->activatedAt,
            isActive:           $this->isActive,
            instanceId:         $this->instanceId,
            revokedAt:          new \DateTimeImmutable(),
            machineFingerprint: $this->machineFingerprint,
        );
    }

    // ── License checks ───────────────────────────────────────────────────────

    public function isTrialActive(): bool
    {
        $trialEnd = $this->installedAt->modify('+' . self::TRIAL_DAYS . ' days');
        return new \DateTimeImmutable() <= $trialEnd;
    }

    public function isRevoked(): bool
    {
        return $this->revokedAt !== null;
    }

    public function isLicensed(): bool
    {
        return $this->isTrialActive() || ($this->isActive && !$this->isRevoked());
    }

    public function getTrialDaysRemaining(): int
    {
        if (!$this->isTrialActive()) {
            return 0;
        }
        $now      = new \DateTimeImmutable();
        $trialEnd = $this->installedAt->modify('+' . self::TRIAL_DAYS . ' days');
        return max(0, (int) $now->diff($trialEnd)->days);
    }

    // ── Getters ──────────────────────────────────────────────────────────────

    public function getId(): string                          { return $this->id; }
    public function getInstallationKey(): string             { return $this->installationKey; }
    public function getActivationKey(): ?string              { return $this->activationKey; }
    public function getInstalledAt(): \DateTimeImmutable     { return $this->installedAt; }
    public function getActivatedAt(): ?\DateTimeImmutable    { return $this->activatedAt; }
    public function isActive(): bool                         { return $this->isActive; }
    public function getInstanceId(): string                  { return $this->instanceId; }
    public function getRevokedAt(): ?\DateTimeImmutable      { return $this->revokedAt; }
    public function getMachineFingerprint(): ?string         { return $this->machineFingerprint; }

    public function toArray(): array
    {
        return [
            'installation_key'     => $this->installationKey,
            'is_trial_active'      => $this->isTrialActive(),
            'trial_days_remaining' => $this->getTrialDaysRemaining(),
            'is_active'            => $this->isActive,
            'activated_at'         => $this->activatedAt?->format(\DateTimeInterface::ATOM),
            'installed_at'         => $this->installedAt->format(\DateTimeInterface::ATOM),
            'is_licensed'          => $this->isLicensed(),
            'is_revoked'           => $this->isRevoked(),
            'revoked_at'           => $this->revokedAt?->format(\DateTimeInterface::ATOM),
        ];
    }
}
