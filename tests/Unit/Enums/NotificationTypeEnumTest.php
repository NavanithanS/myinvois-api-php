<?php

namespace Nava\MyInvois\Tests\Unit\Enums;

use Nava\MyInvois\Enums\NotificationTypeEnum;
use Nava\MyInvois\Tests\TestCase;

class NotificationTypeEnumTest extends TestCase
{
    /** @test */
    public function it_has_correct_values(): void
    {
        // Test that all enum values match expected values
        $this->assertEquals(3, NotificationTypeEnum::PROFILE_DATA_VALIDATION->value);
        $this->assertEquals(6, NotificationTypeEnum::DOCUMENT_RECEIVED->value);
        $this->assertEquals(7, NotificationTypeEnum::DOCUMENT_VALIDATED->value);
        $this->assertEquals(8, NotificationTypeEnum::DOCUMENT_CANCELLED->value);
        $this->assertEquals(10, NotificationTypeEnum::USER_PROFILE_CHANGED->value);
        $this->assertEquals(11, NotificationTypeEnum::TAXPAYER_PROFILE_CHANGED->value);
        $this->assertEquals(15, NotificationTypeEnum::DOCUMENT_REJECTION_INITIATED->value);
        $this->assertEquals(26, NotificationTypeEnum::ERP_DATA_VALIDATION->value);
        $this->assertEquals(33, NotificationTypeEnum::DOCUMENTS_PROCESSING_SUMMARY->value);
        $this->assertEquals(34, NotificationTypeEnum::DOCUMENT_TEMPLATE_PUBLISHED->value);
        $this->assertEquals(35, NotificationTypeEnum::DOCUMENT_TEMPLATE_DELETION->value);
    }

    /** @test */
    public function it_provides_correct_descriptions(): void
    {
        // Test human-readable descriptions
        $this->assertEquals(
            'Profile data validation',
            NotificationTypeEnum::PROFILE_DATA_VALIDATION->description()
        );
        $this->assertEquals(
            'Document received',
            NotificationTypeEnum::DOCUMENT_RECEIVED->description()
        );
        $this->assertEquals(
            'Document validated',
            NotificationTypeEnum::DOCUMENT_VALIDATED->description()
        );
        $this->assertEquals(
            'Document cancelled',
            NotificationTypeEnum::DOCUMENT_CANCELLED->description()
        );
        $this->assertEquals(
            'User profile changed',
            NotificationTypeEnum::USER_PROFILE_CHANGED->description()
        );
        $this->assertEquals(
            'Taxpayer profile changed',
            NotificationTypeEnum::TAXPAYER_PROFILE_CHANGED->description()
        );
        $this->assertEquals(
            'Document rejection initiated',
            NotificationTypeEnum::DOCUMENT_REJECTION_INITIATED->description()
        );
        $this->assertEquals(
            'ERP data validation',
            NotificationTypeEnum::ERP_DATA_VALIDATION->description()
        );
        $this->assertEquals(
            'Documents processing summary',
            NotificationTypeEnum::DOCUMENTS_PROCESSING_SUMMARY->description()
        );
        $this->assertEquals(
            'Document Template Published',
            NotificationTypeEnum::DOCUMENT_TEMPLATE_PUBLISHED->description()
        );
        $this->assertEquals(
            'Document Template Deletion',
            NotificationTypeEnum::DOCUMENT_TEMPLATE_DELETION->description()
        );
    }

    /** @test */
    public function it_validates_type_codes(): void
    {
        // Test valid codes
        $validCodes = [3, 6, 7, 8, 10, 11, 15, 26, 33, 34, 35];
        foreach ($validCodes as $code) {
            $this->assertTrue(
                NotificationTypeEnum::isValidCode($code),
                "Code $code should be valid"
            );
        }

        // Test invalid codes
        $invalidCodes = [-1, 0, 1, 2, 4, 5, 9, 12, 13, 14, 16, 99];
        foreach ($invalidCodes as $code) {
            $this->assertFalse(
                NotificationTypeEnum::isValidCode($code),
                "Code $code should be invalid"
            );
        }
    }

    /** @test */
    public function it_creates_from_valid_code(): void
    {
        // Test creation from valid codes
        $this->assertEquals(
            NotificationTypeEnum::PROFILE_DATA_VALIDATION,
            NotificationTypeEnum::fromCode(3)
        );
        $this->assertEquals(
            NotificationTypeEnum::DOCUMENT_RECEIVED,
            NotificationTypeEnum::fromCode(6)
        );
        $this->assertEquals(
            NotificationTypeEnum::DOCUMENT_VALIDATED,
            NotificationTypeEnum::fromCode(7)
        );
        $this->assertEquals(
            NotificationTypeEnum::DOCUMENT_TEMPLATE_DELETION,
            NotificationTypeEnum::fromCode(35)
        );
    }

    /** @test */
    public function it_throws_exception_for_invalid_code(): void
    {
        $this->expectException(\ValueError::class);
        NotificationTypeEnum::fromCode(999);
    }

    /** @test */
    public function it_provides_all_codes(): void
    {
        $expectedCodes = [3, 6, 7, 8, 10, 11, 15, 26, 33, 34, 35];
        $actualCodes = NotificationTypeEnum::getCodes();

        $this->assertEquals($expectedCodes, $actualCodes);
        $this->assertCount(11, $actualCodes); // Verify total number of notification types
    }

    /** @test */
    public function it_groups_notification_types_by_category(): void
    {
        // Document-related notifications
        $documentTypes = [
            NotificationTypeEnum::DOCUMENT_RECEIVED,
            NotificationTypeEnum::DOCUMENT_VALIDATED,
            NotificationTypeEnum::DOCUMENT_CANCELLED,
            NotificationTypeEnum::DOCUMENT_REJECTION_INITIATED,
            NotificationTypeEnum::DOCUMENTS_PROCESSING_SUMMARY,
        ];

        // Template-related notifications
        $templateTypes = [
            NotificationTypeEnum::DOCUMENT_TEMPLATE_PUBLISHED,
            NotificationTypeEnum::DOCUMENT_TEMPLATE_DELETION,
        ];

        // Profile-related notifications
        $profileTypes = [
            NotificationTypeEnum::PROFILE_DATA_VALIDATION,
            NotificationTypeEnum::USER_PROFILE_CHANGED,
            NotificationTypeEnum::TAXPAYER_PROFILE_CHANGED,
        ];

        // Validation-related notifications
        $validationTypes = [
            NotificationTypeEnum::PROFILE_DATA_VALIDATION,
            NotificationTypeEnum::ERP_DATA_VALIDATION,
            NotificationTypeEnum::DOCUMENT_VALIDATED,
        ];

        // Test that each category contains the expected types
        foreach ($documentTypes as $type) {
            $this->assertStringContainsString('DOCUMENT', $type->name);
        }

        foreach ($templateTypes as $type) {
            $this->assertStringContainsString('TEMPLATE', $type->name);
        }

        foreach ($profileTypes as $type) {
            $this->assertTrue(
                str_contains($type->name, 'PROFILE') ||
                str_contains($type->description(), 'profile')
            );
        }

        foreach ($validationTypes as $type) {
            $this->assertTrue(
                str_contains($type->name, 'VALIDATION') ||
                str_contains($type->description(), 'validation')
            );
        }
    }

    /** @test */
    public function it_is_json_serializable(): void
    {
        $type = NotificationTypeEnum::DOCUMENT_RECEIVED;
        $json = json_encode($type);

        $this->assertEquals(6, json_decode($json));
    }

    /** @test */
    public function it_compares_correctly(): void
    {
        // Test equality
        $type1 = NotificationTypeEnum::DOCUMENT_RECEIVED;
        $type2 = NotificationTypeEnum::DOCUMENT_RECEIVED;
        $type3 = NotificationTypeEnum::DOCUMENT_VALIDATED;

        $this->assertTrue($type1 === $type2);
        $this->assertFalse($type1 === $type3);
        $this->assertEquals($type1, $type2);
        $this->assertNotEquals($type1, $type3);
    }

    /** @test */
    public function it_handles_all_enum_cases(): void
    {
        // Ensure all cases can be accessed
        $cases = NotificationTypeEnum::cases();

        // Verify total number of cases
        $this->assertCount(11, $cases);

        // Verify each case is unique
        $uniqueCases = array_unique(array_map(fn($case) => $case->value, $cases));
        $this->assertCount(11, $uniqueCases);

        // Verify each case has a unique description
        $descriptions = array_map(fn($case) => $case->description(), $cases);
        $uniqueDescriptions = array_unique($descriptions);
        $this->assertCount(11, $uniqueDescriptions);
    }
}
