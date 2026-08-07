<?php

namespace Tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DocumentExtractionTest extends TestCase
{
    public function test_dashboard_route_returns_200(): void
    {
        $response = $this->get('/');
        $response->assertStatus(200);
    }

    public function test_upload_validation_rejects_invalid_mime_type(): void
    {
        $file = UploadedFile::fake()->create('document.txt', 100);
        $response = $this->post('/extract', [
            'file' => $file,
        ]);
        $response->assertStatus(302);
        $response->assertSessionHasErrors('file');
    }

    public function test_upload_validation_rejects_oversized_file(): void
    {
        $file = UploadedFile::fake()->image('document.jpg')->size(12000);
        $response = $this->post('/extract', [
            'file' => $file,
        ]);
        $response->assertStatus(302);
        $response->assertSessionHasErrors('file');
    }

    public function test_successful_extraction_returns_correct_structure(): void
    {
        config(['services.llm.default' => 'openai']);
        config(['services.openai.api_key' => 'test-key']);

        Http::fake([
            'localhost:8001/ocr' => Http::response([
                'text' => 'INV-2001 | Acme Supplies | $20.00 | 2026-07-09',
                'lines' => [],
            ], 200),
            'api.openai.com/*' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'master' => [
                                    'order_code' => 'INV-2001',
                                    'customer_name' => 'Acme Supplies',
                                    'service_classification' => 'BS',
                                    'planned_service_date' => '2026-07-09',
                                    'total_amount' => 20.00,
                                ],
                                'items' => [
                                    [
                                        'service_name1' => 'Widget',
                                        'quantity' => 2,
                                        'unit' => 'pcs',
                                        'unit_price' => 10.00,
                                        'amount' => 20.00,
                                    ],
                                ],
                            ]),
                        ],
                    ],
                ],
            ], 200),
        ]);

        $file = UploadedFile::fake()->image('invoice.jpg');
        $response = $this->post('/extract', [
            'file' => $file,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'preview_url',
                'raw_ocr_text',
                'extracted_data' => [
                    'master',
                    'items',
                ],
                'missing_fields',
            ])
            ->assertJsonPath('extracted_data.master.customer_name', 'Acme Supplies')
            ->assertJsonPath('extracted_data.items.0.service_name1', 'Widget');
    }

    public function test_missing_fields_are_identified(): void
    {
        config(['services.llm.default' => 'openai']);
        config(['services.openai.api_key' => 'test-key']);

        Http::fake([
            'localhost:8001/ocr' => Http::response([
                'text' => "Invoice #1001\nCustomer: Test Corp\nTotal: $0.00\nSome invoice without key fields",
                'lines' => [],
            ], 200),
            'api.openai.com/*' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'master' => [
                                    'order_code' => null,
                                    'customer_name' => null,
                                    'service_classification' => null,
                                    'planned_service_date' => null,
                                    'total_amount' => null,
                                ],
                                'items' => [
                                    [
                                        'service_name1' => 'Unknown item',
                                        'quantity' => 0,
                                        'unit_price' => 0,
                                        'amount' => 0,
                                    ],
                                ],
                            ]),
                        ],
                    ],
                ],
            ], 200),
        ]);

        $file = UploadedFile::fake()->image('blank.jpg');
        $response = $this->post('/extract', [
            'file' => $file,
        ]);

        $response->assertStatus(200);
        $missing = $response->json('missing_fields');
        $this->assertGreaterThanOrEqual(1, count($missing));
    }

    public function test_non_order_document_returns_validation_error(): void
    {
        Http::fake([
            'localhost:8001/ocr' => Http::response([
                'text' => 'A beautiful sunset over the mountains with trees in the foreground',
                'lines' => [],
            ], 200),
        ]);

        $file = UploadedFile::fake()->image('sunset.jpg');
        $response = $this->post('/extract', [
            'file' => $file,
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'error' => 'The uploaded image doesn\'t appear to be a supported order image/document',
        ]);
    }

    public function test_empty_ocr_text_returns_validation_error(): void
    {
        Http::fake([
            'localhost:8001/ocr' => Http::response([
                'text' => '',
                'lines' => [],
            ], 200),
        ]);

        $file = UploadedFile::fake()->image('blank.jpg');
        $response = $this->post('/extract', [
            'file' => $file,
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'error' => 'The uploaded image doesn\'t appear to be a supported order image/document',
        ]);
    }

    public function test_japanese_order_document_passes_validation(): void
    {
        config(['services.llm.default' => 'openai']);
        config(['services.openai.api_key' => 'test-key']);

        Http::fake([
            'localhost:8001/ocr' => Http::response([
                'text' => "請求書 No.2026-0789\n株式会社サンプル様\n合計金額 52,500円\n発行日 2026/07/20",
                'lines' => [],
            ], 200),
            'api.openai.com/*' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'master' => [
                                    'order_code' => '2026-0789',
                                    'customer_name' => '株式会社サンプル',
                                    'total_amount' => 52500,
                                ],
                                'items' => [],
                            ]),
                        ],
                    ],
                ],
            ], 200),
        ]);

        $file = UploadedFile::fake()->image('invoice.jpg');
        $response = $this->post('/extract', [
            'file' => $file,
        ]);

        $response->assertStatus(200);
    }

    public function test_broken_json_is_repaired(): void
    {
        config(['services.llm.default' => 'openai']);
        config(['services.openai.api_key' => 'test-key']);

        Http::fake([
            'localhost:8001/ocr' => Http::response([
                'text' => 'INV-2001 | Acme Supplies',
                'lines' => [],
            ], 200),
            'api.openai.com/*' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => '```json
{
    "master": {
        "order_code": "INV-2001",
        "customer_name": "Acme Supplies",
        "planned_service_date": "2026-07-09",
        "total_amount": 20.00
    },
    "items": [
        {
            "service_name1": "Widget",
            "quantity": 2,
            "unit_price": 10.00,
            "amount": 20.00,
        }
    ]
}```',
                        ],
                    ],
                ],
            ], 200),
        ]);

        $file = UploadedFile::fake()->image('invoice.jpg');
        $response = $this->post('/extract', [
            'file' => $file,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('extracted_data.master.customer_name', 'Acme Supplies');
    }
}
