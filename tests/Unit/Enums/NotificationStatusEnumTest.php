<?php

namespace Nava\MyInvois\Tests\Unit\Enums;

use Nava\MyInvois\Enums\NotificationStatusEnum;
use Nava\MyInvois\Tests\TestCase;

class NotificationStatusEnumTest extends TestCase
{
    /** @test */
    public function it_provides_correct_descriptions(): void
    {
        $this->assertEquals('New', NotificationStatusEnum::NEW->description());
        $this->assertEquals('Pending', NotificationStatusEnum::PENDING->description());
        $this->assertEquals('Batched', NotificationStatusEnum::BATCHED->description());
        $this->assertEquals('Delivered', NotificationStatusEnum::DELIVERED->description());
        $this->assertEquals('Error', NotificationStatusEnum::ERROR->description());
    }

    /** @test */
    public function it_validates_status_codes(): void
    {
        $this->assertTrue(NotificationStatusEnum::isValidCode(1));
        $this->assertTrue(NotificationStatusEnum::isValidCode(2));
        $this->assertTrue(NotificationStatusEnum::isValidCode(3));
        $this->assertTrue(NotificationStatusEnum::isValidCode(4));
        $this->assertTrue(NotificationStatusEnum::isValidCode(5));

        $this->assertFalse(NotificationStatusEnum::isValidCode(0));
        $this->assertFalse(NotificationStatusEnum::isValidCode(6));
    }

    /** @test */
    public function it_creates_from_valid_code(): void
    {
        $this->assertEquals(NotificationStatusEnum::NEW, NotificationStatusEnum::fromCode(1));
        $this->assertEquals(NotificationStatusEnum::PENDING, NotificationStatusEnum::fromCode(2));
        $this->assertEquals(NotificationStatusEnum::BATCHED, NotificationStatusEnum::fromCode(3));
        $this->assertEquals(NotificationStatusEnum::DELIVERED, NotificationStatusEnum::fromCode(4));
        $this->assertEquals(NotificationStatusEnum::ERROR, NotificationStatusEnum::fromCode(5));
    }

    /** @test */
    public function it_creates_from_valid_name(): void
    {
        $this->assertEquals(NotificationStatusEnum::NEW, NotificationStatusEnum::fromName('NEW'));
        $this->assertEquals(NotificationStatusEnum::PENDING, NotificationStatusEnum::fromName('PENDING'));
        $this->assertEquals(NotificationStatusEnum::BATCHED, NotificationStatusEnum::fromName('BATCHED'));
        $this->assertEquals(NotificationStatusEnum::DELIVERED, NotificationStatusEnum::fromName('DELIVERED'));
        $this->assertEquals(NotificationStatusEnum::ERROR, NotificationStatusEnum::fromName('ERROR'));

        // Test case-insensitive
        $this->assertEquals(NotificationStatusEnum::NEW, NotificationStatusEnum::fromName('new'));
        $this->assertEquals(NotificationStatusEnum::PENDING, NotificationStatusEnum::fromName('pending'));
    }

    /** @test */
    public function it_throws_exception_for_invalid_name(): void
    {
        $this->expectException(\ValueError::class);
        NotificationStatusEnum::fromName('INVALID');
    }

    /** @test */
    public function it_correctly_identifies_final_states(): void
    {
        $this->assertFalse(NotificationStatusEnum::NEW->isFinal());
        $this->assertFalse(NotificationStatusEnum::PENDING->isFinal());
        $this->assertFalse(NotificationStatusEnum::BATCHED->isFinal());
        $this->assertTrue(NotificationStatusEnum::DELIVERED->isFinal());
        $this->assertTrue(NotificationStatusEnum::ERROR->isFinal());
    }

    /** @test */
    public function it_correctly_identifies_successful_states(): void
    {
        $this->assertFalse(NotificationStatusEnum::NEW->isSuccessful());
        $this->assertFalse(NotificationStatusEnum::PENDING->isSuccessful());
        $this->assertFalse(NotificationStatusEnum::BATCHED->isSuccessful());
        $this->assertTrue(NotificationStatusEnum::DELIVERED->isSuccessful());
        $this->assertFalse(NotificationStatusEnum::ERROR->isSuccessful());
    }

    /** @test */
    public function it_correctly_identifies_error_states(): void
    {
        $this->assertFalse(NotificationStatusEnum::NEW->isError());
        $this->assertFalse(NotificationStatusEnum::PENDING->isError());
        $this->assertFalse(NotificationStatusEnum::BATCHED->isError());
        $this->assertFalse(NotificationStatusEnum::DELIVERED->isError());
        $this->assertTrue(NotificationStatusEnum::ERROR->isError());
    }

    /** @test */
    public function it_correctly_identifies_in_progress_states(): void
    {
        $this->assertTrue(NotificationStatusEnum::NEW->isInProgress());
        $this->assertTrue(NotificationStatusEnum::PENDING->isInProgress());
        $this->assertTrue(NotificationStatusEnum::BATCHED->isInProgress());
        $this->assertFalse(NotificationStatusEnum::DELIVERED->isInProgress());
        $this->assertFalse(NotificationStatusEnum::ERROR->isInProgress());
    }

    /** @test */
    public function it_provides_valid_transitions(): void
    {
        // Test NEW transitions
        $newTransitions = NotificationStatusEnum::NEW->getValidTransitions();
        $this->assertContains(NotificationStatusEnum::PENDING, $newTransitions);
        $this->assertContains(NotificationStatusEnum::BATCHED, $newTransitions);
        $this->assertNotContains(NotificationStatusEnum::DELIVERED, $newTransitions);

        // Test PENDING transitions
        $pendingTransitions = NotificationStatusEnum::PENDING->getValidTransitions();
        $this->assertContains(NotificationStatusEnum::BATCHED, $pendingTransitions);
        $this->assertContains(NotificationStatusEnum::DELIVERED, $pendingTransitions);
        $this->assertContains(NotificationStatusEnum::ERROR, $pendingTransitions);

        // Test final states have no transitions
        $this->assertEmpty(NotificationStatusEnum::DELIVERED->getValidTransitions());
        $this->assertEmpty(NotificationStatusEnum::ERROR->getValidTransitions());
    }

    /** @test */
    public function it_validates_status_transitions(): void
    {
        // Test valid transitions
        $this->assertTrue(NotificationStatusEnum::NEW->canTransitionTo(NotificationStatusEnum::PENDING));
        $this->assertTrue(NotificationStatusEnum::PENDING->canTransitionTo(NotificationStatusEnum::DELIVERED));
        $this->assertTrue(NotificationStatusEnum::BATCHED->canTransitionTo(NotificationStatusEnum::ERROR));

        // Test invalid transitions
        $this->assertFalse(NotificationStatusEnum::DELIVERED->canTransitionTo(NotificationStatusEnum::PENDING));
        $this->assertFalse(NotificationStatusEnum::ERROR->canTransitionTo(NotificationStatusEnum::DELIVERED));
        $this->assertFalse(NotificationStatusEnum::NEW->canTransitionTo(NotificationStatusEnum::DELIVERED));
    }
}
