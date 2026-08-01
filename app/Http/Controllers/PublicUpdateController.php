<?php

namespace App\Http\Controllers;

use App\Models\HatcheryUpdate;
use Illuminate\Http\Request;

class PublicUpdateController extends Controller
{
    public function show($id)
    {
        $update = HatcheryUpdate::with(['hatchery', 'vendor', 'location'])->find($id);

        if (!$update) {
            abort(404, 'Update not found');
        }

        // Resolve media files
        $mediaFiles = [];
        if (is_array($update->media_files)) {
            foreach ($update->media_files as $file) {
                $url = str_starts_with($file, 'http') ? $file : asset($file);
                $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                $isVideo = in_array($ext, ['mp4', 'mov', 'avi', 'webm']);
                $mediaFiles[] = [
                    'url' => $url,
                    'is_video' => $isVideo,
                ];
            }
        } elseif ($update->media_path) {
            $url = str_starts_with($update->media_path, 'http') ? $update->media_path : asset($update->media_path);
            $ext = strtolower(pathinfo($update->media_path, PATHINFO_EXTENSION));
            $isVideo = in_array($ext, ['mp4', 'mov', 'avi', 'webm']);
            $mediaFiles[] = [
                'url' => $url,
                'is_video' => $isVideo,
            ];
        }

        // Resolve hatchery logo
        $logo = null;
        if ($update->hatchery && $update->hatchery->logo) {
            $logo = str_starts_with($update->hatchery->logo, 'http')
                ? $update->hatchery->logo
                : asset($update->hatchery->logo);
        }

        // Parse hashtags
        $hashtags = [];
        if ($update->hashtags) {
            $hashtags = is_array($update->hashtags) ? $update->hashtags : json_decode($update->hashtags, true) ?? [];
        }

        return view('public.hatchery-update', [
            'update' => $update,
            'mediaFiles' => $mediaFiles,
            'logo' => $logo,
            'hashtags' => $hashtags,
        ]);
    }
}
