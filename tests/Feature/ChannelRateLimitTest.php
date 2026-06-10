<?php

namespace Tests\Feature;

use App\Enums\Channel;
use App\Jobs\Middleware\ChannelRateLimited;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class ChannelRateLimitTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Redis::connection()->client()->flushdb();
        config(['services.gateways.rate_limits.sms' => 1]);
    }

    public function test_job_exceeding_channel_rate_limit_is_released_without_provider_call(): void
    {
        $middleware = new ChannelRateLimited;

        $first = new FakeJobContext(Channel::Sms);
        $second = new FakeJobContext(Channel::Sms);

        $firstRan = $secondRan = false;
        $middleware->handle($first, function () use (&$firstRan) {
            $firstRan = true;
        });
        $middleware->handle($second, function () use (&$secondRan) {
            $secondRan = true;
        });

        $this->assertTrue($firstRan, 'первый job проходит лимит 1 msg/sec');
        $this->assertFalse($first->released);

        $this->assertFalse($secondRan, 'второй job в ту же секунду не должен дойти до провайдера');
        $this->assertTrue($second->released, 'job возвращается в очередь, попытки не расходуются');
    }
}

class FakeJobContext
{
    public bool $released = false;

    public function __construct(public Channel $channel) {}

    public function release(int $delay = 0): void
    {
        $this->released = true;
    }
}
