<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use App\Models\SystemEnum;
use App\Traits\BuildsPaginationMeta;

class SystemEnumController extends Controller
{
    use BuildsPaginationMeta;
    //

    public function fetchEnum(Request $request)
    {
        $raw = $request->input('etype');
        $etype = is_string($raw) ? trim($raw) : null;

        if ($etype === null || $etype === '') {
            $data = SystemEnum::all();
            return response()->json([
                'success' => true,
                'data' => $data
            ], 200);
        }

        $data = SystemEnum::where('etype', $etype)->get();

        return response()->json([
            'success' => true,
            'data' => $data
        ], 200);
    }

    public function fetchEnumList(Request $request)
    {
        // Page size bounds
        $perPage = (int) $request->input('per_page', 25);
        $perPage = max(1, min(100, $perPage));

        // Basic search across etype and name
        $search = trim((string) $request->input('search', ''));

        // Sorting (whitelist fields)
        $allowedSort = ['id','etype','name','created_at'];
        $sort = $request->input('sort', 'id');
        if (!in_array($sort, $allowedSort, true)) { $sort = 'id'; }
        $direction = strtolower($request->input('direction', 'desc')) === 'asc' ? 'asc' : 'desc';

        // Soft delete filters
        $withDeleted = $request->boolean('with_deleted', false);
        $onlyDeleted = $request->boolean('only_deleted', false);

        $query = SystemEnum::query();
        if ($withDeleted || $onlyDeleted) {
            $query->withTrashed();
        }
        if ($onlyDeleted) {
            $query->whereNotNull('deleted_at');
        }
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('etype', 'like', "%$search%")
                  ->orWhere('name', 'like', "%$search%");
            });
        }

        $paginator = $query->orderBy($sort, $direction)->paginate($perPage); // 'page' param auto-used

        // Transform items
        $items = collect($paginator->items())->map(function ($enum) {
            return [
                'id' => $enum->id,
                'etype' => $enum->etype,
                'name' => $enum->name,
                'created_at' => $enum->created_at,
                'updated_at' => $enum->updated_at,
                'deleted_at' => $enum->deleted_at,
            ];
        });

        return $this->paginatedResponse(
            $paginator,
            $items,
            [
                'search' => $search,
                'sort' => $sort,
                'direction' => $direction,
                'with_deleted' => $withDeleted,
                'only_deleted' => $onlyDeleted,
            ]
        );
    }

    public function createEnum(Request $request)
    {
        $validated = $request->validate([
            'etype' => ['required', 'string', 'max:255', 'regex:/^([a-z0-9]+)(\.[a-z0-9]+)+$/i'],
            'name' => ['required', 'string', 'max:255'],
        ]);

        $etype = trim($validated['etype']);
        $name = trim($validated['name']);

        $exists = SystemEnum::where('etype', $etype)
            ->where('name', $name)
            ->exists();

        if ($exists) {
            return response()->json([
                'success' => false,
                'code' => 'EnumAlreadyExists',
                'message' => 'An enum with the same etype and name already exists.'
            ], 409);
        }

        // Determine next seqid for this etype
        $currentMax = SystemEnum::where('etype', $etype)->max('seqid');
        $nextSeqId = is_null($currentMax) ? 0 : ($currentMax + 1);

        $enum = SystemEnum::create([
            'etype' => $etype,
            'name' => $name,
            'seqid' => $nextSeqId,
        ]);

        return response()->json([
            'success' => true,
            'data' => $enum
        ], 201);
    }

    public function updateEnum(Request $request, $id)
    {
        $validated = $request->validate([
            'etype' => ['sometimes', 'string', 'max:255', 'regex:/^([a-z0-9]+)(\.[a-z0-9]+)+$/i'],
            'name' => ['sometimes', 'string', 'max:255'],
        ]);

        $enum = SystemEnum::find((int)$id);
        if (!$enum) {
            return response()->json([
                'success' => false,
                'code' => 'EnumNotFound',
            ], 404);
        }

        $originalEtype = $enum->etype;
        $originalSeqid = $enum->seqid;

        $newEtype = isset($validated['etype']) ? trim($validated['etype']) : $originalEtype;
        $newName = isset($validated['name']) ? trim($validated['name']) : $enum->name;

        // Prevent duplicate (etype, name)
        $duplicate = SystemEnum::where('etype', $newEtype)
            ->where('name', $newName)
            ->where('id', '!=', $enum->id)
            ->exists();
        if ($duplicate) {
            return response()->json([
                'success' => false,
                'code' => 'EnumAlreadyExists',
                'message' => 'Another enum with the same etype and name already exists.'
            ], 409);
        }

        // If etype changed, move the record to the end of the new etype and close gap in old etype
        if ($newEtype !== $originalEtype) {
            // Close gap in old etype
            SystemEnum::where('etype', $originalEtype)
                ->where('seqid', '>', $originalSeqid)
                ->decrement('seqid', 1);

            // Assign next seqid in new etype
            $currentMax = SystemEnum::where('etype', $newEtype)->max('seqid');
            $enum->seqid = is_null($currentMax) ? 0 : ($currentMax + 1);
        }

        $enum->etype = $newEtype;
        $enum->name = $newName;
        $enum->save();

        return response()->json([
            'success' => true,
            'data' => $enum
        ], 200);
    }

    public function softDeleteEnum(Request $request, $id)
    {
        $id = (int) $id;

        $enum = SystemEnum::find($id);
        if (!$enum) {
            return response()->json([
                'success' => false,
                'code' => 'EnumNotFound',
            ], 404);
        }

        // Capture etype and seqid before delete
        $etype = $enum->etype;
        $deletedSeqid = $enum->seqid;

        // Soft delete
        $enum->delete();

        // Renumber decrement seqid for items with seqid greater than deleted one
        SystemEnum::where('etype', $etype)
            ->where('seqid', '>', $deletedSeqid)
            ->decrement('seqid', 1);

        return response()->json([
            'success' => true,
            'message' => 'Enum soft-deleted and seqid renumbered',
            'id' => $id,
        ], 200);
    }

    public function createDummyEnum(Request $request)
    {
        // Testing utility: create many dummy enums for a given etype
        $validated = $request->validate([
            'etype' => ['sometimes', 'string', 'max:255', 'regex:/^([a-z0-9]+)(\.[a-z0-9]+)+$/i'],
            'count' => ['sometimes', 'integer', 'min:1', 'max:500'],
        ]);

        $etype = isset($validated['etype']) ? trim($validated['etype']) : 'temp.enum';
        $count = isset($validated['count']) ? (int)$validated['count'] : 500;

        // Determine starting seqid for this etype
        $currentMax = SystemEnum::where('etype', $etype)->max('seqid');
        $startSeq = is_null($currentMax) ? 0 : ($currentMax + 1);

        $now = now();
        $batch = [];
        for ($i = 0; $i < $count; $i++) {
            $batch[] = [
                'etype' => $etype,
                'name' => 'Temporary Enum ' . ($startSeq + $i),
                'seqid' => $startSeq + $i,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        // Efficient bulk insert
        SystemEnum::insert($batch);

        return response()->json([
            'success' => true,
            'inserted' => $count,
            'etype' => $etype,
            'start_seqid' => $startSeq,
            'end_seqid' => $startSeq + $count - 1,
        ], 201);
    }
}
