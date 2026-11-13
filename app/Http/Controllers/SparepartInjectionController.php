<?php

namespace App\Http\Controllers;

use App\Imports\SparepartInjectionSheet;
use App\Models\SparepartInjectionImport;
use App\Models\User;
use App\Services\SparepartInjectionService;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Sparepart Injection tab of Master > Sparepart. ADMIN/KOORDINATOR upload;
 * SUPERVISOR is view-only; PIC has no access (route middleware plus the
 * explicit checks here).
 */
class SparepartInjectionController extends Controller
{
    public function index(Request $request)
    {
        $user = $this->viewer($request);

        return view('sparepart-injection.index', [
            'imports' => SparepartInjectionImport::query()
                ->visibleTo($user)
                ->with('user:id,name')
                ->latest('id')
                ->paginate(20),
            'canImport' => $this->canImport($user),
        ]);
    }

    public function show(Request $request, SparepartInjectionImport $import)
    {
        $user = $this->viewer($request);
        abort_unless(SparepartInjectionImport::visibleTo($user)->whereKey($import->id)->exists(), 403);

        $status = in_array($request->query('status'), ['PROCESSED', 'SKIPPED', 'FAILED'], true) ? $request->query('status') : null;

        return view('sparepart-injection.show', [
            'import' => $import->load('user:id,name'),
            'rows' => $import->rows()
                ->when($status, fn ($q) => $q->where('status', $status))
                ->orderBy('row_number')
                ->paginate(20)
                ->withQueryString(),
            'status' => $status,
        ]);
    }

    public function store(Request $request, SparepartInjectionService $service)
    {
        $user = $request->user();
        abort_unless($this->canImport($user), 403);

        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'max:5120'],
        ]);

        $file = $request->file('file');

        try {
            $sheet = Excel::toArray(new SparepartInjectionSheet, $file)[0] ?? [];
            $import = $service->import($user, $file->getClientOriginalName(), $sheet);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['file' => $e->getMessage()]);
        } catch (\Throwable $e) {
            report($e);

            return back()->withErrors(['file' => 'The import failed and nothing was saved. Check the file, then try again.']);
        }

        return redirect()
            ->route('sparepart-injection.show', $import)
            ->with('success', 'Import selesai.');
    }

    private function viewer(Request $request): User
    {
        $user = $request->user();
        abort_unless($user->isAdmin() || $user->isKoordinator() || $user->isSupervisor(), 403);

        return $user;
    }

    private function canImport(User $user): bool
    {
        return $user->isAdmin() || $user->isKoordinator();
    }
}
