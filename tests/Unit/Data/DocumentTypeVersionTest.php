<?php

namespace Nava\MyInvois\Tests\Unit\Data;

use DateTimeImmutable;
use Nava\MyInvois\Data\DocumentTypeVersion;
use Nava\MyInvois\Tests\TestCase;

class DocumentTypeVersionTest extends TestCase
{
    private array $validData;

    protected function setUp(): void
    {
        parent::setUp();

        $this->validData = [
            'id' => 454,
            'name' => '1.0',
            'description' => 'Version 1.0',
            'activeFrom' => '2024-01-01T00:00:00Z',
            'activeTo' => null,
            'versionNumber' => 1.0,
            'status' => 'published',
        ];
    }

    /** @test */
    public function it_creates_from_valid_data(): void
    {
        $version = DocumentTypeVersion::fromArray($this->validData);

        $this->assertEquals(454, $version->id);
        $this->assertEquals('1.0', $version->name);
        $this->assertEquals('Version 1.0', $version->description);
        $this->assertInstanceOf(DateTimeImmutable::class, $version->activeFrom);
        $this->assertNull($version->activeTo);
        $this->assertEquals(1.0, $version->versionNumber);
        $this->assertEquals('published', $version->status);
    }

    /** @test */
    public function it_validates_required_fields(): void
    {
        $requiredFields = ['id', 'name', 'description', 'activeFrom', 'versionNumber', 'status'];

        foreach ($requiredFields as $field) {
            $invalidData = $this->validData;
            unset($invalidData[$field]);

            $this->expectException(\InvalidArgumentException::class);
            DocumentTypeVersion::fromArray($invalidData);
        }
    }

    /** @test */
    public function it_handles_different_date_formats(): void
    {
        $dateFormats = [
            '2024-01-01T00:00:00Z',
            '2024-01-01 00:00:00',
            '2024-01-01',
        ];

        foreach ($dateFormats as $dateFormat) {
            $data = $this->validData;
            $data['activeFrom'] = $dateFormat;

            $version = DocumentTypeVersion::fromArray($data);
            $this->assertInstanceOf(DateTimeImmutable::class, $version->activeFrom);
        }
    }

    /** @test */
    public function it_validates_status_values(): void
    {
        $validStatuses = ['draft', 'published', 'deactivated'];

        foreach ($validStatuses as $status) {
            $data = $this->validData;
            $data['status'] = $status;

            $version = DocumentTypeVersion::fromArray($data);
            $this->assertEquals($status, $version->status);
        }

        $data = $this->validData;
        $data['status'] = 'invalid_status';

        $this->expectException(\InvalidArgumentException::class);
        DocumentTypeVersion::fromArray($data);
    }

    /** @test */
    public function it_correctly_determines_active_status(): void
    {
        $now = new DateTimeImmutable;
        $past = $now->modify('-1 year');
        $future = $now->modify('+1 year');

        // Test published status with no end date
        $version = new DocumentTypeVersion([
            'id' => 1,
            'name' => '1.0',
            'description' => 'Test',
            'activeFrom' => $past,
            'activeTo' => null,
            'versionNumber' => 1.0,
            'status' => 'published',
        ]);
        $this->assertTrue($version->isActive());

        // Test published status with future end date
        $version = new DocumentTypeVersion([
            'id' => 1,
            'name' => '1.0',
            'description' => 'Test',
            'activeFrom' => $past,
            'activeTo' => $future,
            'versionNumber' => 1.0,
            'status' => 'published',
        ]);
        $this->assertTrue($version->isActive());

        // Test draft status
        $version = new DocumentTypeVersion([
            'id' => 1,
            'name' => '1.0',
            'description' => 'Test',
            'activeFrom' => $past,
            'activeTo' => null,
            'versionNumber' => 1.0,
            'status' => 'draft',
        ]);
        $this->assertFalse($version->isActive());

        // Test deactivated status
        $version = new DocumentTypeVersion([
            'id' => 1,
            'name' => '1.0',
            'description' => 'Test',
            'activeFrom' => $past,
            'activeTo' => null,
            'versionNumber' => 1.0,
            'status' => 'deactivated',
        ]);
        $this->assertFalse($version->isActive());

        // Test expired version
        $version = new DocumentTypeVersion([
            'id' => 1,
            'name' => '1.0',
            'description' => 'Test',
            'activeFrom' => $past,
            'activeTo' => $past,
            'versionNumber' => 1.0,
            'status' => 'published',
        ]);
        $this->assertFalse($version->isActive());

        // Test future version
        $version = new DocumentTypeVersion([
            'id' => 1,
            'name' => '1.0',
            'description' => 'Test',
            'activeFrom' => $future,
            'activeTo' => null,
            'versionNumber' => 1.0,
            'status' => 'published',
        ]);
        $this->assertFalse($version->isActive());
    }

    /** @test */
    public function it_validates_version_number_format(): void
    {
        $validVersionNumbers = [1.0, 1.1, 2.0, 2.1];

        foreach ($validVersionNumbers as $versionNumber) {
            $data = $this->validData;
            $data['versionNumber'] = $versionNumber;

            $version = DocumentTypeVersion::fromArray($data);
            $this->assertEquals($versionNumber, $version->versionNumber);
        }

        $invalidVersionNumbers = [-1.0, 0.0, 'invalid'];

        foreach ($invalidVersionNumbers as $versionNumber) {
            $data = $this->validData;
            $data['versionNumber'] = $versionNumber;

            $this->expectException(\InvalidArgumentException::class);
            DocumentTypeVersion::fromArray($data);
        }
    }

    /** @test */
    public function it_handles_json_serialization(): void
    {
        $version = DocumentTypeVersion::fromArray($this->validData);
        $json = json_encode($version);
        $decoded = json_decode($json, true);

        $this->assertIsString($json);
        $this->assertEquals($version->id, $decoded['id']);
        $this->assertEquals($version->name, $decoded['name']);
        $this->assertEquals($version->description, $decoded['description']);
        $this->assertEquals($version->versionNumber, $decoded['versionNumber']);
        $this->assertEquals($version->status, $decoded['status']);
    }
}
