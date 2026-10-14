<?php

use App\Models\Document;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
});

test('it stores an image document and verifies a matching upload without authentication', function () {
    $stored = $this->post('/api/documents', [
        'document' => UploadedFile::fake()->image('identity-card.png'),
        'name' => 'Identity card',
    ]);

    $stored->assertCreated()
        ->assertJsonPath('data.name', 'Identity card');

    $document = Document::findOrFail($stored->json('data.id'));
    Storage::disk('local')->assertExists($document->path);

    $verification = $this->post("/api/documents/{$document->id}/verify", [
        'document' => UploadedFile::fake()->image('identity-card-copy.png'),
    ]);

    $verification->assertOk()
        ->assertJsonPath('data.document_id', $document->id)
        ->assertJsonPath('data.verified', true)
        ->assertJsonPath('data.threshold', 85);

    expect($verification->json('data.similarity'))->toBeGreaterThanOrEqual(85);
});

test('it rejects non-image documents', function () {
    $this->post('/api/documents', [
        'document' => UploadedFile::fake()->create('document.pdf', 10, 'application/pdf'),
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('document');
});
