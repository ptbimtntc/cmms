<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\Machine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GroupController extends Controller
{
    public function index(Request $request)
    {
        $query = Group::withCount('machines');

        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        $groups = $query->orderBy('name', 'asc')
            ->paginate(20)
            ->withQueryString();

        return view('groups.index', compact('groups'));
    }

    public function create()
    {
        return view('groups.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:groups,name',
        ]);

        Group::create([
            'name' => $request->name,
        ]);

        return redirect()
            ->route('groups.index')
            ->with('success', 'Group created successfully');
    }

    public function edit(Group $group)
    {
        $machines = Machine::with('group')
            ->orderBy('machine_number')
            ->get();

        return view('groups.edit', compact('group', 'machines'));
    }

    public function update(Request $request, Group $group)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:groups,name,' . $group->id,
            'machine_ids' => 'nullable|array',
            'machine_ids.*' => 'exists:machines,id',
        ]);

        $selectedMachineIds = $validated['machine_ids'] ?? [];

        DB::transaction(function () use ($group, $validated, $selectedMachineIds) {
            $group->update([
                'name' => $validated['name'],
            ]);

            // Assign the checked machines to this group (may move them out of
            // whatever group they previously belonged to — a machine only
            // ever has one group_id column, never a pivot).
            if (! empty($selectedMachineIds)) {
                Machine::whereIn('id', $selectedMachineIds)->update(['group_id' => $group->id]);
            }

            // Any machine currently in this group that was unchecked is
            // removed from the group (set back to ungrouped).
            Machine::where('group_id', $group->id)
                ->whereNotIn('id', $selectedMachineIds)
                ->update(['group_id' => null]);
        });

        return redirect()
            ->route('groups.index')
            ->with('success', 'Group updated successfully');
    }

    public function destroy(Group $group)
    {
        if ($group->machines()->exists()) {
            return back()->with('error', 'Cannot delete a group that still has machines assigned to it.');
        }

        if ($group->greasings()->exists()) {
            return back()->with('error', 'Cannot delete a group that still has greasing schedules assigned to it.');
        }

        $group->delete();

        return back()->with('success', 'Group deleted');
    }
}
