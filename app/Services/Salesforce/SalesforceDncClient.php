<?php

namespace App\Services\Salesforce;

use Illuminate\Http\Client\Response;
use RuntimeException;

class SalesforceDncClient
{
    public const API_PATH = '/services/data/v64.0/sobjects/DNC__c/';

    public function __construct(
        private readonly SalesforceClient $salesforce,
    ) {}

    /**
     * @param  array<string, mixed>  $record
     */
    public function insert(array $record): SalesforceDncInsertResult
    {
        $response = $this->salesforce->http()->post(
            '/services/data/'.$this->salesforce->apiVersion().'/sobjects/DNC__c/',
            $record,
        );

        if ($response->status() === 201) {
            $id = $response->json('id');

            if (! is_string($id) || $id === '') {
                return SalesforceDncInsertResult::failure('Salesforce DNC insert succeeded without an id.');
            }

            return SalesforceDncInsertResult::success($id);
        }

        if ($response->serverError()) {
            throw new RuntimeException('Salesforce DNC insert failed: HTTP '.$response->status().': '.$response->body());
        }

        return SalesforceDncInsertResult::failure($this->formatError($response));
    }

    private function formatError(Response $response): string
    {
        $json = $response->json();

        if (is_array($json) && array_is_list($json) && isset($json[0]) && is_array($json[0])) {
            $first = $json[0];
            $code = isset($first['errorCode']) && is_string($first['errorCode']) ? $first['errorCode'] : null;
            $message = isset($first['message']) && is_string($first['message']) ? $first['message'] : null;

            if ($code !== null && $message !== null) {
                return $code.': '.$message;
            }

            if ($message !== null) {
                return $message;
            }
        }

        $body = trim($response->body());

        return 'HTTP '.$response->status().($body !== '' ? ': '.$body : '');
    }
}
