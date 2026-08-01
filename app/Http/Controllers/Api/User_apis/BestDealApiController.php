<?php

namespace App\Http\Controllers\Api\User_apis;

use App\Http\Controllers\Controller;
use App\Models\BestDeal;
use Exception;

class BestDealApiController extends Controller
{
    public function index()
    {
        try {
            $deals = BestDeal::where('is_active', 1)
                ->orderBy('sort_order', 'asc')
                ->orderBy('id', 'desc')
                ->get()
                ->map(fn($item) => $this->formatList($item));

            return response()->json([
                'status' => true,
                'message' => 'Best deals fetched successfully',
                'data' => $deals,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error: ' . $e->getMessage(),
                'data' => [],
            ]);
        }
    }

    public function show($id)
    {
        try {
            $deal = BestDeal::where('id', $id)
                ->where('is_active', 1)
                ->first();

            if (!$deal) {
                return response()->json([
                    'status' => false,
                    'message' => 'Best deal not found',
                    'data' => null,
                ], 404);
            }

            return response()->json([
                'status' => true,
                'message' => 'Best deal details fetched successfully',
                'data' => $this->formatDetail($deal),
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error: ' . $e->getMessage(),
                'data' => null,
            ]);
        }
    }

    private function formatList($item)
    {
        if (!empty($item->media_files) && is_array($item->media_files)) {
            $mediaFiles = array_map(fn($path) => asset($path), $item->media_files);
            $mediaTypes = $item->media_types ?? [];
        } elseif ($item->media_path) {
            $mediaFiles = [asset($item->media_path)];
            $mediaTypes = [$item->media_type ?? 'image'];
        } else {
            $mediaFiles = [];
            $mediaTypes = [];
        }

        return [
            'id' => $item->id,
            'title' => $item->title ?? '',
            'subtitle' => $item->subtitle ?? '',
            'media_path' => $item->media_path ? asset($item->media_path) : '',
            'media_type' => $item->media_type ?? '',
            'media_files' => $mediaFiles,
            'media_types' => $mediaTypes,
            'created_at' => $item->created_at ? $item->created_at->format('d-m-Y') : null,
        ];
    }

    private function formatDetail($item)
    {
        $list = $this->formatList($item);

        return array_merge($list, [
            'description' => $item->description ?? '',
            'call_number' => $item->call_number
                ? 'tel:' . preg_replace('/\D/', '', $item->call_number)
                : '',
            'whatsapp_number' => $item->whatsapp_number
                ? 'https://wa.me/' . preg_replace('/\D/', '', $item->whatsapp_number)
                : '',
        ]);
    }
}
