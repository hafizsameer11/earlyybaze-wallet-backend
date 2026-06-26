<?php

namespace App\DTO;

final class OnChainVerificationResult
{
    public const FAIL_TX_NOT_FOUND = 'tx_not_found';

    public const FAIL_TX_FAILED = 'tx_failed';

    public const FAIL_ADDRESS_MISMATCH = 'address_mismatch';

    public const FAIL_AMOUNT_MISMATCH = 'amount_mismatch';

    public const FAIL_CONTRACT_MISMATCH = 'contract_mismatch';

    public const FAIL_TX_DROPPED = 'tx_dropped';

    public const FAIL_FLUSH_SUBMIT = 'flush_submit_failed';

    public const FAIL_FLUSH_MISSING_KEY = 'flush_missing_key';

    public const FAIL_FLUSH_GAS_TOPUP = 'flush_gas_topup_failed';

    public const FAIL_FLUSH_INSUFFICIENT = 'flush_insufficient_balance';

    public const FAIL_FLUSH_MISSING_TXID = 'flush_missing_txid';

    public function __construct(
        public bool $found = false,
        public bool $confirmed = false,
        public bool $matches = false,
        public ?string $from = null,
        public ?string $to = null,
        public ?string $amount = null,
        public ?string $contract = null,
        public ?int $logIndex = null,
        public ?int $blockNumber = null,
        public ?string $failureCode = null,
        public ?string $failureMessage = null,
        public array $raw = [],
    ) {}

    public static function notFound(string $message = 'Transaction not found on chain'): self
    {
        return new self(
            found: false,
            confirmed: false,
            matches: false,
            failureCode: self::FAIL_TX_NOT_FOUND,
            failureMessage: $message,
        );
    }

    public static function flushSubmitFailed(string $code, string $message, array $raw = []): self
    {
        return new self(
            found: false,
            confirmed: false,
            matches: false,
            failureCode: $code,
            failureMessage: $message,
            raw: $raw,
        );
    }

    public function isSuccess(): bool
    {
        return $this->found && $this->confirmed && $this->matches && $this->failureCode === null;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'found' => $this->found,
            'confirmed' => $this->confirmed,
            'matches' => $this->matches,
            'from' => $this->from,
            'to' => $this->to,
            'amount' => $this->amount,
            'contract' => $this->contract,
            'log_index' => $this->logIndex,
            'block_number' => $this->blockNumber,
            'failure_code' => $this->failureCode,
            'failure_message' => $this->failureMessage,
        ];
    }
}
