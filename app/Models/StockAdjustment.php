<?php

namespace App\Models;

use App\Services\StockAdjustmentPresenter;
use Illuminate\Database\Eloquent\Model;

/**
 * 1 lần chủ quán SỬA TRỰC TIẾP tồn kho / giá vốn TB của nguyên liệu (v1.1.3).
 * Chỉ ghi thêm, không có chức năng sửa/xoá dòng nhật ký — xem migration để biết lý do tồn tại.
 */
class StockAdjustment extends Model
{
    public const REASONS = [
        'kiem_ke' => 'Kiểm kê thực tế',
        'hao_hut' => 'Hao hụt / hư hỏng / hết hạn',
        'sai_so_lieu' => 'Sửa số liệu nhập sai',
        'khac' => 'Lý do khác',
    ];

    /**
     * Những lý do phản ánh chênh lệch HÀNG THẬT (kiểm đếm lệch sổ, hàng hỏng...) —
     * dùng để cộng thành "thiếu hụt/dư" ở trang nhật ký. "Sửa số liệu nhập sai"
     * cố tình KHÔNG nằm đây: đó là sửa lại sổ sách cho đúng, không phải hàng
     * hoá thật sự mất đi hay dư ra.
     */
    public const PHYSICAL_REASONS = ['kiem_ke', 'hao_hut', 'khac'];

    /** Lý do bắt buộc kèm ghi chú (vì bản thân lý do không nói lên điều gì). */
    public const REASON_NEEDS_NOTE = 'khac';

    protected $fillable = [
        'location_id', 'ingredient_id', 'user_id', 'reason',
        'stock_before', 'stock_after', 'cost_before', 'cost_after', 'note',
    ];

    protected function casts(): array
    {
        return [
            'stock_before' => 'decimal:2',
            'stock_after' => 'decimal:2',
            'cost_before' => 'decimal:4',
            'cost_after' => 'decimal:4',
        ];
    }

    public function location()
    {
        return $this->belongsTo(Location::class);
    }

    /** withTrashed: nguyên liệu đã xoá mềm vẫn phải hiện tên trong nhật ký cũ. */
    public function ingredient()
    {
        return $this->belongsTo(Ingredient::class)->withTrashed();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function reasonLabel(): string
    {
        return self::REASONS[$this->reason] ?? $this->reason;
    }

    public function stockDelta(): float
    {
        return StockAdjustmentPresenter::delta((float) $this->stock_before, (float) $this->stock_after);
    }

    public function costChanged(): bool
    {
        return StockAdjustmentPresenter::costChanged((float) $this->cost_before, (float) $this->cost_after);
    }

    /**
     * Giá trị ước tính (đ) của lượng tồn bị giảm — chênh lệch × giá vốn TB TẠI
     * THỜI ĐIỂM sửa. null khi tồn không giảm hoặc chưa có giá vốn để quy đổi.
     */
    public function shortageValue(): ?float
    {
        return StockAdjustmentPresenter::shortageValue(
            (float) $this->stock_before, (float) $this->stock_after, (float) $this->cost_before
        );
    }

    /**
     * Dữ liệu ĐÃ ĐỊNH DẠNG kiểu Việt Nam để hiển thị — dùng chung cho cả JSON
     * (panel "Lịch sử" ở trang Kho) và Blade (trang Nhật ký) để 2 nơi không bao
     * giờ hiển thị khác nhau. Định dạng ngay tại server, không viết lại logic
     * format tiền/số lượng lần nữa ở JavaScript. Phần số liệu do
     * StockAdjustmentPresenter tính (thuần, có test).
     */
    public function display(?string $unit = null): array
    {
        $unit ??= $this->ingredient?->unit ?? '';

        return [
            'id' => $this->id,
            'ingredient_name' => $this->ingredient?->name ?? '(đã xoá)',
            'ingredient_trashed' => (bool) $this->ingredient?->trashed(),
            'reason' => $this->reason,
            'reason_label' => $this->reasonLabel(),
            'created_at' => $this->created_at->format('d/m/Y H:i'),
            'user_name' => $this->user?->name ?? 'Không rõ',
            'note' => $this->note,
        ] + StockAdjustmentPresenter::numbers(
            (float) $this->stock_before, (float) $this->stock_after,
            (float) $this->cost_before, (float) $this->cost_after,
            $unit,
            in_array($this->reason, self::PHYSICAL_REASONS, true),
        );
    }
}
