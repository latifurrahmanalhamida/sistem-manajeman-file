<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\File;
use App\Models\Folder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TrashController extends Controller
{
    /**
     * Display a listing of the trashed resources.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $user = Auth::user();

        // All users can see the trash of their own division.
        // No authorization check is needed here because the query is already scoped.
        $trashedFiles = File::onlyTrashed()
            ->where('division_id', $user->division_id)
            ->get();

        $trashedFolders = Folder::onlyTrashed()
            ->where('division_id', $user->division_id)
            ->get();

        return response()->json([
            'message' => 'Successfully retrieved trashed items.',
            'data' => [
                'files' => $trashedFiles,
                'folders' => $trashedFolders,
            ]
        ]);
    }
}
