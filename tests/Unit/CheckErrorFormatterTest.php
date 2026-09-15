<?php

namespace Tests\Unit;

use App\Support\CheckErrorFormatter;
use Tests\TestCase;

class CheckErrorFormatterTest extends TestCase
{
    public function test_plain_text_stays_the_message(): void
    {
        $parsed = CheckErrorFormatter::parse('Company Salesforce ID is not set (surveyCompanyId).');

        $this->assertNull($parsed['summary']);
        $this->assertNull($parsed['code']);
        $this->assertSame('Company Salesforce ID is not set (surveyCompanyId).', $parsed['message']);
        $this->assertNull($parsed['details']);
    }

    public function test_formats_salesforce_query_json_array(): void
    {
        $parsed = CheckErrorFormatter::parse(
            'Salesforce query failed: [{"message":"\nPhone_Cleaned__c FROM Booking__c\nERROR at Row:1:Column:22\nNo such column \'Phone_Cleaned__c\' on entity \'Booking__c\'.","errorCode":"INVALID_FIELD"}]',
        );

        $this->assertSame('Salesforce query failed', $parsed['summary']);
        $this->assertSame('INVALID_FIELD', $parsed['code']);
        $this->assertStringContainsString("No such column 'Phone_Cleaned__c' on entity 'Booking__c'.", $parsed['message']);
        $this->assertStringContainsString('ERROR at Row:1:Column:22', $parsed['message']);
        $this->assertStringContainsString('"errorCode": "INVALID_FIELD"', (string) $parsed['details']);
    }

    public function test_formats_http_json_object(): void
    {
        $parsed = CheckErrorFormatter::parse(
            'HTTP 401: {"error":"invalid_client","error_description":"client identifier invalid"}',
        );

        $this->assertSame('HTTP 401', $parsed['summary']);
        $this->assertSame('invalid_client', $parsed['code']);
        $this->assertSame('client identifier invalid', $parsed['message']);
        $this->assertStringContainsString('"error": "invalid_client"', (string) $parsed['details']);
    }

    public function test_pretty_prints_json_without_a_human_message(): void
    {
        $parsed = CheckErrorFormatter::parse('HTTP 500: {"status":"down","code":12}');

        $this->assertSame('HTTP 500', $parsed['summary']);
        $this->assertSame('500', $parsed['code']);
        $this->assertStringContainsString('"status": "down"', $parsed['message']);
        $this->assertNull($parsed['details']);
    }

    public function test_strips_html_bodies(): void
    {
        $parsed = CheckErrorFormatter::parse('HTTP 502: <html><body><h1>Bad Gateway</h1></body></html>');

        $this->assertSame('HTTP 502', $parsed['summary']);
        $this->assertSame('502', $parsed['code']);
        $this->assertSame('Bad Gateway', $parsed['message']);
        $this->assertNull($parsed['details']);
    }
}
