<?php

namespace App\Services;

/**
 * Quyết định form "Sửa nguyên liệu" THỰC SỰ đổi gì về Tồn kho / Giá vốn TB (v1.1.3).
 *
 * Class thuần (không đụng database) để kiểm thử độc lập; InventoryController
 * gọi trong transaction sau khi đã khoá dòng nguyên liệu.
 *
 * Vấn đề nó giải quyết: form gửi lên CẢ tồn kho lẫn giá vốn dù người dùng chỉ
 * muốn đổi tên. Nếu ghi thẳng như trước, 2 lỗi xảy ra:
 *  1. Trong lúc form mở (vài phút) có đơn bán ra làm tồn kho giảm — bấm Lưu sẽ
 *     ghi ĐÈ tồn kho cũ (đã lỗi thời) lên số đúng, mất số lượng vừa bán.
 *  2. Không phân biệt được "có ý sửa tồn kho" với "chỉ đổi tên" → không ghi
 *     nhật ký chính xác.
 * Giải pháp: form gửi kèm giá trị GỐC lúc mở (original_*). Chỉ khi giá trị gửi
 * lên KHÁC giá trị gốc mới coi là người dùng chủ ý sửa; ngược lại giữ nguyên số
 * đang có trong database. Thiếu giá trị gốc (request cũ/tự dựng) thì so sánh
 * thẳng với database.
 */
class IngredientEditPlan
{
    public const STOCK_SCALE = 2;

    public const COST_SCALE = 4;

    /**
     * @return array{stock: float, cost: float, stockChanged: bool, costChanged: bool, changed: bool}
     */
    public static function make(
        float $dbStock,
        float $dbCost,
        float $submittedStock,
        float $submittedCost,
        ?float $originalStock = null,
        ?float $originalCost = null,
    ): array {
        $stock = self::resolve($dbStock, $submittedStock, $originalStock, self::STOCK_SCALE);
        $cost = self::resolve($dbCost, $submittedCost, $originalCost, self::COST_SCALE);

        $stockChanged = ! self::same($stock, $dbStock, self::STOCK_SCALE);
        $costChanged = ! self::same($cost, $dbCost, self::COST_SCALE);

        return [
            'stock' => $stock,
            'cost' => $cost,
            'stockChanged' => $stockChanged,
            'costChanged' => $costChanged,
            'changed' => $stockChanged || $costChanged,
        ];
    }

    /** Giá trị cuối cùng: người dùng chủ ý sửa → lấy số gửi lên (làm tròn đúng độ chính xác cột); không → giữ số trong DB. */
    private static function resolve(float $db, float $submitted, ?float $original, int $scale): float
    {
        $edited = $original === null
            ? ! self::same($submitted, $db, $scale)
            : ! self::same($submitted, $original, $scale);

        return $edited ? round($submitted, $scale) : round($db, $scale);
    }

    private static function same(float $a, float $b, int $scale): bool
    {
        return round($a, $scale) === round($b, $scale);
    }
}
