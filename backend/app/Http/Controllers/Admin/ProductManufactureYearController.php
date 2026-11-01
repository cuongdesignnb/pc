<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Support\SimpleXlsx;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Năm sản xuất (NĐ 37/2026) for many products at once: set one year for the
 * ticked products or for every product matching the list filters, or fill a
 * downloaded Excel sheet and import it back.
 */
class ProductManufactureYearController extends Controller
{
    private const HEADERS = ['ID', 'SKU', 'Tên sản phẩm', 'Danh mục', 'Năm sản xuất'];

    /** The product list filters, shared with the list page. */
    public static function applyFilters(Builder $query, Request $request): Builder
    {
        if (filled($request->search)) {
            $search = (string) $request->search;
            $query->where(fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('sku', 'like', "%{$search}%"));
        }
        if (filled($request->category_id)) {
            $query->where('category_id', $request->category_id);
        }
        if (filled($request->brand_id)) {
            $query->where('brand_id', $request->brand_id);
        }
        if ($request->status === 'active') {
            $query->where('is_active', true);
        } elseif ($request->status === 'inactive') {
            $query->where('is_active', false);
        }
        if ($request->manufacture_year === 'missing') {
            $query->whereNull('manufacture_year');
        } elseif (filled($request->manufacture_year) && ctype_digit((string) $request->manufacture_year)) {
            $query->where('manufacture_year', (int) $request->manufacture_year);
        }

        return $query;
    }

    public function bulk(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'year' => ['nullable', 'integer', 'min:1990', 'max:'.(now()->year + 1)],
            'product_ids' => ['required_without:all_filtered', 'array'],
            'product_ids.*' => ['integer'],
            'all_filtered' => ['boolean'],
        ]);

        $query = Product::query();
        if ($request->boolean('all_filtered')) {
            self::applyFilters($query, $request);
        } else {
            $query->whereIn('id', $validated['product_ids']);
        }
        $count = $query->update(['manufacture_year' => $validated['year'] ?? null]);

        return back()->with('success', $validated['year'] ?? null
            ? "Đã đặt năm sản xuất {$validated['year']} cho {$count} sản phẩm."
            : "Đã xoá năm sản xuất của {$count} sản phẩm.");
    }

    public function template(Request $request): BinaryFileResponse
    {
        $rows = function () use ($request) {
            $query = self::applyFilters(Product::query()->with('category:id,name'), $request)->orderBy('id');
            foreach ($query->lazy(500) as $product) {
                yield [$product->id, $product->sku, $product->name, $product->category?->name, $product->manufacture_year];
            }
        };

        return SimpleXlsx::download('nam-san-xuat-'.now()->format('Ymd').'.xlsx', self::HEADERS, $rows());
    }

    public function import(Request $request): RedirectResponse
    {
        $request->validate(['file' => ['required', 'file', 'mimes:xlsx,csv,txt', 'max:10240']]);
        $file = $request->file('file');
        $rows = Str::lower($file->getClientOriginalExtension()) === 'xlsx'
            ? SimpleXlsx::read($file->getPathname())
            : $this->csvRows($file->getPathname());

        $header = array_map(fn ($cell) => Str::lower(Str::ascii(trim((string) $cell))), array_shift($rows) ?? []);
        $idColumn = array_search('id', $header, true);
        $skuColumn = array_search('sku', $header, true);
        $yearColumn = array_search('nam san xuat', $header, true);
        if ($yearColumn === false || ($idColumn === false && $skuColumn === false)) {
            return back()->with('error', 'File cần có cột "Năm sản xuất" và cột "ID" hoặc "SKU". Hãy tải file mẫu rồi điền vào.');
        }

        $updated = 0;
        $errors = [];
        $maxYear = now()->year + 1;
        foreach ($rows as $index => $row) {
            $line = $index + 2;
            $yearText = trim((string) ($row[$yearColumn] ?? ''));
            if ($yearText === '') {
                continue;
            }
            $year = (int) preg_replace('/\.0+$/', '', $yearText);
            if (! preg_match('/^\d{4}(\.0+)?$/', $yearText) || $year < 1990 || $year > $maxYear) {
                $errors[] = "dòng {$line}: năm \"{$yearText}\" không hợp lệ";

                continue;
            }
            $id = $idColumn !== false ? (int) ($row[$idColumn] ?? 0) : 0;
            $sku = $skuColumn !== false ? trim((string) ($row[$skuColumn] ?? '')) : '';
            $product = $id > 0 ? Product::find($id) : null;
            $product ??= $sku !== '' ? Product::where('sku', $sku)->first() : null;
            if (! $product) {
                $errors[] = "dòng {$line}: không tìm thấy sản phẩm";

                continue;
            }
            if ($product->manufacture_year !== $year) {
                $product->forceFill(['manufacture_year' => $year])->save();
                $updated++;
            }
        }

        $message = "Đã cập nhật năm sản xuất cho {$updated} sản phẩm.";
        if ($errors !== []) {
            $shown = array_slice($errors, 0, 10);
            $more = count($errors) > 10 ? ' (và '.(count($errors) - 10).' lỗi khác)' : '';

            return back()->with('error', $message.' Bỏ qua: '.implode('; ', $shown).$more.'.');
        }

        return back()->with('success', $message);
    }

    /** @return list<list<string>> */
    private function csvRows(string $path): array
    {
        $content = (string) file_get_contents($path);
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content) ?? $content;
        $lines = preg_split('/\r\n|\n|\r/', trim($content)) ?: [];
        $delimiter = substr_count($lines[0] ?? '', ';') > substr_count($lines[0] ?? '', ',') ? ';' : ',';

        return array_map(fn ($line) => str_getcsv($line, $delimiter), $lines);
    }
}
