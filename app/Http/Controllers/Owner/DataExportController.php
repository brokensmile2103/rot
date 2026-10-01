<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\ResolvesCurrentLocation;
use App\Services\DataExportService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Illuminate\Support\Str;

/**
 * Xuất toàn bộ dữ liệu của quán ĐANG CHỌN (current_location_id) ra 1 file
 * ZIP — cùng quy ước "current location" như mọi trang Cài đặt/Báo cáo khác
 * (chủ quán có nhiều xe thì xuất từng xe riêng, không gộp chung).
 */
class DataExportController extends Controller
{
    use ResolvesCurrentLocation;

    public function export(Request $request, DataExportService $exporter): BinaryFileResponse
    {
        $location = $this->currentLocation($request);

        $zipPath = $exporter->buildZip($location);

        $filename = Str::slug($location->name.'-du-lieu-'.now()->format('Y-m-d')).'.zip';

        // response()->download() trả BinaryFileResponse — khai báo kiểu Response như trước đây
        // làm PHP ném TypeError, nút "Xuất dữ liệu" luôn báo lỗi 500.
        return response()->download($zipPath, $filename)->deleteFileAfterSend(true);
    }
}
