<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        // env() читает $_SERVER (реальное окружение контейнера) раньше,
        // чем $_ENV, куда PHPUnit пишет <env force="true"> из phpunit.xml.
        // Без выравнивания тесты молча ходят в dev-базу и реальный RabbitMQ
        // вместо notifications_test и sync-очереди.
        foreach ($_ENV as $name => $value) {
            $_SERVER[$name] = $value;
        }

        parent::setUp();
    }
}
