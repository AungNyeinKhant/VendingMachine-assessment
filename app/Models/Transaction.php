<?php

namespace App\Models;

use Database\Factories\TransactionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A purchase log entry. Money columns are price snapshots taken when the
 * purchase happened, so later product price changes never rewrite history.
 *
 * @property int $id
 * @property int $user_id
 * @property int $product_id
 * @property int $quantity
 * @property string $unit_price DECIMAL(10,3) as string
 * @property string $total_price DECIMAL(12,3) as string
 * @property string $amount_paid DECIMAL(12,3) as string
 * @property string $change_given DECIMAL(12,3) as string
 * @property Carbon|null $created_at
 */
#[Fillable(['user_id', 'product_id', 'quantity', 'unit_price', 'total_price', 'amount_paid', 'change_given'])]
class Transaction extends Model
{
    /** @use HasFactory<TransactionFactory> */
    use HasFactory;

    public $timestamps = false;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'decimal:3',
            'total_price' => 'decimal:3',
            'amount_paid' => 'decimal:3',
            'change_given' => 'decimal:3',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
