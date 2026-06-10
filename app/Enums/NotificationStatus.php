<?php

namespace App\Enums;

enum NotificationStatus: string
{
    /** Принято и ожидает отправки */
    case Queued = 'queued';

    /** Внутренний переходный статус: воркер захватил сообщение и вызывает провайдера */
    case Sending = 'sending';

    /** Передано шлюзу/провайдеру */
    case Sent = 'sent';

    /** Доставка подтверждена провайдером */
    case Delivered = 'delivered';

    /** Отброшено: ошибка доставки, несуществующий номер/email и т.д. */
    case Failed = 'failed';

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Queued => [self::Sending, self::Failed],
            // Sending -> Queued: возврат в очередь при transient-ошибке (ретрай)
            self::Sending => [self::Sent, self::Failed, self::Queued],
            self::Sent => [self::Delivered, self::Failed],
            self::Delivered, self::Failed => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    public function isFinal(): bool
    {
        return $this->allowedTransitions() === [];
    }
}
