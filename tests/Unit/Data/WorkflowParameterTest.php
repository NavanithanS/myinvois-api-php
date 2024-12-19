<?php

namespace Nava\MyInvois\Tests\Unit\Data;

use DateTimeImmutable;
use Nava\MyInvois\Data\WorkflowParameter;
use Nava\MyInvois\Tests\TestCase;

class WorkflowParameterTest extends TestCase
{
    private array $validData;

    protected function setUp(): void
    {
        parent::setUp();

        $this->validData = [
            'id' => 124,
            'parameter' => 'rejectionDuration',
            'value' => 72,
            'activeFrom' => '2024-01-01T00:00:00Z',
            'activeTo' => null,
        ];
    }

    /** @test */
    public function it_creates_from_valid_data(): void
    {
        $parameter = WorkflowParameter::fromArray($this->validData);

        $this->assertEquals(124, $parameter->id);
        $this->assertEquals('rejectionDuration', $parameter->parameter);
        $this->assertEquals(72, $parameter->value);
        $this->assertInstanceOf(DateTimeImmutable::class, $parameter->activeFrom);
        $this->assertNull($parameter->activeTo);
    }

    /** @test */
    public function it_validates_required_fields(): void
    {
        $requiredFields = ['id', 'parameter', 'value', 'activeFrom'];

        foreach ($requiredFields as $field) {
            $invalidData = $this->validData;
            unset($invalidData[$field]);

            $this->expectException(\InvalidArgumentException::class);
            WorkflowParameter::fromArray($invalidData);
        }
    }

    /** @test */
    public function it_validates_parameter_names(): void
    {
        $this->validData['parameter'] = 'invalidParameter';

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Parameter must be one of: submissionDuration, cancellationDuration, rejectionDuration');

        WorkflowParameter::fromArray($this->validData);
    }

    /** @test */
    public function it_validates_value_is_positive(): void
    {
        $invalidValues = [-1, 0, 'invalid'];

        foreach ($invalidValues as $value) {
            $this->validData['value'] = $value;

            $this->expectException(\InvalidArgumentException::class);
            WorkflowParameter::fromArray($this->validData);
        }
    }

    /** @test */
    public function it_correctly_determines_active_status(): void
    {
        $now = new DateTimeImmutable;
        $past = $now->modify('-1 year');
        $future = $now->modify('+1 year');

        // Active parameter (no end date)
        $parameter = new WorkflowParameter([
            'id' => 1,
            'parameter' => 'rejectionDuration',
            'value' => 72,
            'activeFrom' => $past,
            'activeTo' => null,
        ]);
        $this->assertTrue($parameter->isActive());

        // Active parameter (future end date)
        $parameter = new WorkflowParameter([
            'id' => 1,
            'parameter' => 'rejectionDuration',
            'value' => 72,
            'activeFrom' => $past,
            'activeTo' => $future,
        ]);
        $this->assertTrue($parameter->isActive());

        // Inactive parameter (past end date)
        $parameter = new WorkflowParameter([
            'id' => 1,
            'parameter' => 'rejectionDuration',
            'value' => 72,
            'activeFrom' => $past,
            'activeTo' => $past,
        ]);
        $this->assertFalse($parameter->isActive());
    }

    /** @test */
    public function it_handles_json_serialization(): void
    {
        $parameter = WorkflowParameter::fromArray($this->validData);
        $json = json_encode($parameter);
        $decoded = json_decode($json, true);

        $this->assertIsString($json);
        $this->assertEquals($parameter->id, $decoded['id']);
        $this->assertEquals($parameter->parameter, $decoded['parameter']);
        $this->assertEquals($parameter->value, $decoded['value']);
    }
}
