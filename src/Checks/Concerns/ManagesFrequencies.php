<?php

namespace NetOs\Vitals\Checks\Concerns;

trait ManagesFrequencies
{
    /**
     * The cron expression for this check. Null means the default of the scope it is registered in.
     */
    protected ?string $expression = null;

    public function cron(string $expression): static
    {
        $this->expression = $expression;

        return $this;
    }

    public function everyMinute(): static
    {
        return $this->cron('* * * * *');
    }

    public function everyFiveMinutes(): static
    {
        return $this->cron('*/5 * * * *');
    }

    public function everyTenMinutes(): static
    {
        return $this->cron('*/10 * * * *');
    }

    public function everyFifteenMinutes(): static
    {
        return $this->cron('*/15 * * * *');
    }

    public function everyThirtyMinutes(): static
    {
        return $this->cron('*/30 * * * *');
    }

    public function hourly(): static
    {
        return $this->cron('0 * * * *');
    }

    public function hourlyAt(int $minute): static
    {
        return $this->cron("{$minute} * * * *");
    }

    public function daily(): static
    {
        return $this->cron('0 0 * * *');
    }

    public function dailyAt(string $time): static
    {
        [$hour, $minute] = array_map('intval', explode(':', $time.':0'));

        return $this->cron("{$minute} {$hour} * * *");
    }

    public function expression(): ?string
    {
        return $this->expression;
    }
}
