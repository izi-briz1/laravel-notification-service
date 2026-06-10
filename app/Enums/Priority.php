<?php

namespace App\Enums;

enum Priority: string
{
    case Transactional = 'transactional';
    case Marketing = 'marketing';

    /**
     * Имя очереди RabbitMQ: транзакционный трафик идёт в выделенную
     * очередь со своими воркерами и не ждёт за маркетинговым.
     */
    public function queue(): string
    {
        return $this->value;
    }
}
