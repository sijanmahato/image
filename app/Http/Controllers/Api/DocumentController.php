<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Document;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\ImageHash\ImageHasher;
use Intervention\ImageHash\Strategies\DifferenceStrategy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class DocumentController extends Controller
{
    private const VERIFICATION_THRESHOLD = 85.0;

    public function store(Request $request)
    {
        $validated = $request->validate([
            'document' => ['required', 'image', 'mimes:jpg,jpeg,png,gif,webp', 'max:10240'],
            'name' => ['sometimes', 'string', 'max:255'],
        ]);

        $file = $request->file('document');
        $path = $file->store('documents', 'local');

        if (! is_string($path)) {
            throw new RuntimeException('Unable to store the uploaded document.');
        }

        $mimeType = $file->getMimeType();

        if (! is_string($mimeType)) {
            throw new RuntimeException('Unable to determine the uploaded document type.');
        }

        $document = Document::create([
            'name' => Str::limit($validated['name'] ?? $file->getClientOriginalName(), 255, ''),
            'path' => $path,
            'mime_type' => $mimeType,
            'size' => $file->getSize(),
        ]);

        return response()->json([
            'message' => 'Document stored successfully.',
            'data' => [
                'id' => $document->id,
                'name' => $document->name,
                'mime_type' => $document->mime_type,
                'size' => $document->size,
            ],
        ], 201);
    }

    public function verify(Request $request, Document $document)
    {
        $request->validate([
            'document' => ['required', 'image', 'mimes:jpg,jpeg,png,gif,webp', 'max:10240'],
        ]);

        $disk = Storage::disk('local');
        $storedPath = $disk->path($document->path);

        if (! $disk->exists($document->path)) {
            abort(404, 'The stored document file is missing.');
        }

        $hasher = new ImageHasher(new GdDriver(), new DifferenceStrategy());
        $storedHash = $hasher->hash($storedPath);
        $uploadedHash = $hasher->hash($request->file('document')->getPathname());
        $similarity = (1 - ($storedHash->distance($uploadedHash) / $storedHash->bitLength())) * 100;

        return response()->json([
            'data' => [
                'document_id' => $document->id,
                'verified' => $similarity >= self::VERIFICATION_THRESHOLD,
                'similarity' => $similarity,
                'threshold' => self::VERIFICATION_THRESHOLD,
            ],
        ]);
    }
}
