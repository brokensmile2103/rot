<?php

namespace App\Services;

/**
 * Phần TÍNH TOÁN + ĐỊNH DẠNG SỐ của 1 dòng nhật ký điều chỉnh kho (v1.1.3).
 *
 * Tách khỏi model StockAdjustment thành class thuần (không phụ thuộc Eloquent)
 * để kiểm thử độc lập được các phép tính dễ sai: chênh lệch tồn kho, dấu +/−,
 * giá trị thiếu hụt quy đổi theo giá vốn. Model chỉ ủy quyền sang đây, nên
 * trang nhật ký, panel "Lịch sử" và file xuất CSV luôn ra cùng 1 kết quả.
 *
 * Dùng các helper toàn cục quantity()/money() (app/helpers.php) để định dạng số
 * kiểu Việt Nam — cùng 1 quy ước với phần còn lại của ứng dụng.
 */
class StockAdjustmentPresenter
{
    /** Chênh lệch tồn kho (sau − trước), làm tròn đúng độ chính xác cột (2 chữ số thập phân). */
    public static function delta(float $stockBefore, float $stockAfter): float
    {
        return round($stockAfter - $stockBefore, 2);
    }

    /** Giá vốn TB có bị đổi không — so ở đúng độ chính xác cột (4 chữ số thập phân). */
    public static function costChanged(float $costBefore, float $costAfter): bool
    {
        return round($costBefore, 4) !== round($costAfter, 4);
    }

    /**
     * Giá trị ước tính (đ) của lượng tồn bị GIẢM, quy đổi theo giá vốn TB tại
     * thời điểm sửa. null nếu tồn không giảm hoặc chưa có giá vốn để quy đổi.
     */
    public static function shortageValue(float $stockBefore, float $stockAfter, float $costBefore): ?float
    {
        $delta = self::delta($stockBefore, $stockAfter);
        if ($delta >= 0 || $costBefore <= 0) {
            return null;
        }

        return abs($delta) * $costBefore;
    }

    /**
     * @param  bool  $physical  Lý do có phải loại phản ánh chênh lệch HÀNG THẬT không (chỉ khi đó mới hiện giá trị thiếu hụt)
     * @return array<string, mixed>
     */
    public static function numbers(
        float $stockBefore,
        float $stockAfter,
        float $costBefore,
        float $costAfter,
        string $unit,
        bool $physical,
    ): array {
        $delta = self::delta($stockBefore, $stockAfter);
        $shortage = self::shortageValue($stockBefore, $stockAfter, $costBefore);

        return [
            'stock_changed' => $delta !== 0.0,
            'stock_before' => quantity($stockBefore).' '.$unit,
            'stock_after' => quantity($stockAfter).' '.$unit,
            'stock_delta' => ($delta > 0 ? '+' : '−').quantity(abs($delta)).' '.$unit,
            'delta_sign' => $delta > 0 ? 'up' : ($delta < 0 ? 'down' : 'flat'),
            'cost_changed' => self::costChanged($costBefore, $costAfter),
            'cost_before' => quantity($costBefore, 4).'đ/'.$unit,
            'cost_after' => quantity($costAfter, 4).'đ/'.$unit,
            'shortage_value' => ($physical && $shortage !== null) ? '≈ '.money($shortage).'đ' : null,
        ];
    }
}
