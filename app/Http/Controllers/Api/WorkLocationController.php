<?php

namespace App\Http\Controllers\Api;

use App\Models\WorkLocation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkLocationController
{
    public function index(Request $request): JsonResponse
    {
        $query = WorkLocation::query();

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $locations = $query->orderBy('name')->get();

        return response()->json(['success' => true, 'data' => $locations]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'          => 'required|string|max:255',
            'address'       => 'nullable|string',
            'latitude'      => 'required|numeric|between:-90,90',
            'longitude'     => 'required|numeric|between:-180,180',
            'radius_meters' => 'required|integer|min:10|max:10000',
            'is_active'     => 'boolean',
            'notes'         => 'nullable|string',
        ]);

        $location = WorkLocation::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'تم إنشاء الموقع بنجاح',
            'data'    => $location,
        ], 201);
    }

    public function show($id): JsonResponse
    {
        $location = WorkLocation::findOrFail($id);

        return response()->json(['success' => true, 'data' => $location]);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $location = WorkLocation::findOrFail($id);

        $validated = $request->validate([
            'name'          => 'sometimes|string|max:255',
            'address'       => 'nullable|string',
            'latitude'      => 'sometimes|numeric|between:-90,90',
            'longitude'     => 'sometimes|numeric|between:-180,180',
            'radius_meters' => 'sometimes|integer|min:10|max:10000',
            'is_active'     => 'boolean',
            'notes'         => 'nullable|string',
        ]);

        $location->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث الموقع بنجاح',
            'data'    => $location,
        ]);
    }

    public function destroy($id): JsonResponse
    {
        $location = WorkLocation::findOrFail($id);
        $location->delete();

        return response()->json(['success' => true, 'message' => 'تم حذف الموقع بنجاح']);
    }
}
