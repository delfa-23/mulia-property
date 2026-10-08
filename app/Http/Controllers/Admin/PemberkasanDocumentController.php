<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\GoogleDriveToken;
use App\Models\Lot;
use App\Models\PemberkasanDocument;
use App\Models\Property;
use App\Services\PemberkasanDocumentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PemberkasanDocumentController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'property_id' => ['nullable', 'integer', 'exists:properties,id'],
            'lot_id' => ['nullable', 'integer', 'exists:lots,id'],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
        ]);
        $propertyId = $filters['property_id'] ?? null;
        $lotOptions = collect();
        $documentFilters = function ($query) use ($propertyId, $filters): void {
            if ($propertyId !== null) {
                $query->where(function ($query) use ($propertyId) {
                    $query->where('property_id', $propertyId)
                        ->orWhereHas('lot.block', fn ($block) => $block->where('property_id', $propertyId));
                });
            }

            if (! empty($filters['lot_id'])) {
                $query->where('lot_id', $filters['lot_id']);
            }
        };

        if ($propertyId !== null) {
            $lotOptions = Lot::query()
                ->with('block:id,name')
                ->whereHas('block', fn ($block) => $block->where('property_id', $propertyId))
                ->orderBy('block_id')
                ->orderBy('lot_number')
                ->get(['id', 'block_id', 'lot_number']);
        }

        $customerQuery = Customer::query()->whereHas('pemberkasanDocuments', $documentFilters);

        return view('admin.google-drive.documents.index', [
            'customers' => (clone $customerQuery)
                ->withCount(['pemberkasanDocuments as documents_count' => $documentFilters])
                ->with(['pemberkasanDocuments' => function ($query) use ($documentFilters): void {
                    $documentFilters($query);
                    $query->select(['customer_id', 'property_id', 'lot_id'])
                        ->distinct()
                        ->with([
                            'property:id,name',
                            'lot:id,block_id,lot_number',
                            'lot.block:id,name,property_id',
                            'lot.block.property:id,name',
                        ]);
                }])
                ->when($filters['customer_id'] ?? null, fn ($query, $customerId) => $query->whereKey($customerId))
                ->orderBy('name')
                ->paginate(25)
                ->withQueryString(),
            'customerOptions' => $customerQuery->orderBy('name')->get(['id', 'name']),
            'properties' => Property::query()->orderBy('name')->get(['id', 'name']),
            'lotOptions' => $lotOptions,
            'googleDriveConnected' => $this->isConnectedToTargetAccount(),
            'googleDriveAccountEmail' => config('services.google_drive.account_email'),
        ]);
    }

    public function customerDocuments(Customer $customer): View
    {
        return view('admin.google-drive.documents.customer', [
            'customer' => $customer,
            'documents' => $customer->pemberkasanDocuments()
                ->with(['property', 'lot.block'])
                ->latest()
                ->paginate(25)
                ->withQueryString(),
            'googleDriveConnected' => $this->isConnectedToTargetAccount(),
            'googleDriveAccountEmail' => config('services.google_drive.account_email'),
        ]);
    }

    private function isConnectedToTargetAccount(): bool
    {
        $connectedAccount = GoogleDriveToken::query()->value('account_email');
        $targetAccount = config('services.google_drive.account_email');

        return is_string($connectedAccount)
            && is_string($targetAccount)
            && mb_strtolower($connectedAccount) === mb_strtolower($targetAccount);
    }

    public function retry(
        Request $request,
        PemberkasanDocument $pemberkasanDocument,
        PemberkasanDocumentService $documentService,
    ): RedirectResponse {
        try {
            $document = $documentService->retryUpload($pemberkasanDocument, $request->user());

            return back()->with(
                'success',
                $document->status === 'uploaded'
                    ? 'Upload ulang dokumen berhasil.'
                    : 'Upload ulang dokumen telah ditambahkan ke antrean Google Drive.',
            );
        } catch (\Throwable $exception) {
            return back()->withErrors(['document' => $exception->getMessage()]);
        }
    }

    public function replace(
        Request $request,
        PemberkasanDocument $pemberkasanDocument,
        PemberkasanDocumentService $documentService,
    ): RedirectResponse {
        $validated = $request->validate([
            'document' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ]);

        try {
            $documentService->replaceDocument(
                $pemberkasanDocument,
                $validated['document'],
                $request->user(),
            );

            return back()->with(
                'success',
                'File pengganti disimpan dan sedang menunggu upload ke Google Drive.',
            );
        } catch (\Throwable $exception) {
            return back()->withErrors(['document' => $exception->getMessage()]);
        }
    }

    public function destroy(
        PemberkasanDocument $pemberkasanDocument,
        PemberkasanDocumentService $documentService,
    ): RedirectResponse {
        try {
            $documentService->deleteDocument($pemberkasanDocument);

            return back()->with('success', 'Dokumen berhasil dihapus dari Google Drive dan aplikasi.');
        } catch (\Throwable $exception) {
            return back()->withErrors(['document' => $exception->getMessage()]);
        }
    }
}
