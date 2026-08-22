<?php

namespace App\Http\Controllers;

use App\Models\Group;
use Illuminate\Http\Request;

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
        return view('groups.edit', compact('group'));
    }

    public function update(Request $request, Group $group)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:groups,name,' . $group->id,
        ]);

        $group->update([
            'name' => $request->name,
        ]);

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
