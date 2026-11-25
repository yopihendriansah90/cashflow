<?php

namespace App\Http\Controllers;

use App\Support\Brand;
use App\Support\Ledger;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class IdentityController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->is_system_admin, 403);

        $membership = $request->user()->activeMembership()->first();
        $request->attributes->set('membership', $membership);

        return view('pages.cashflow.identity', compact('membership'));
    }

    public function update(Request $request)
    {
        abort_unless($request->user()->is_system_admin, 403);
        $data = $this->validateCashflow($request, [
            'app_name' => ['required', 'string', 'max:120'],
            'reset_name' => ['sometimes', 'boolean'],
            'reset_logo' => ['sometimes', 'boolean'],
            'reset_favicon' => ['sometimes', 'boolean'],
            'logo' => ['nullable', 'file', 'max:1024', 'mimetypes:image/png,image/webp'],
            'favicon' => ['nullable', 'file', 'max:1024', 'mimetypes:image/png'],
        ], [
            'logo.max' => __('Logo maksimal 1 MB.'),
            'favicon.max' => __('Favicon maksimal 1 MB.'),
            'logo.mimetypes' => __('Logo harus berupa PNG atau WebP yang valid.'),
            'favicon.mimetypes' => __('Favicon harus berupa PNG yang valid.'),
        ]);

        $paths = [];
        try {
            foreach (['logo', 'favicon'] as $kind) {
                if ($data['reset_'.$kind] ?? false) {
                    $paths[$kind.'_path'] = null;
                } elseif (isset($data[$kind])) {
                    $paths[$kind.'_path'] = $this->storeImage($data[$kind], $kind);
                }
            }

            $retired = DB::transaction(function () use ($request, $data, $paths) {
                abort_unless($request->user()->newQuery()->lockForUpdate()->findOrFail($request->user()->id)->is_system_admin, 403);
                $settings = DB::table('system_settings')->lockForUpdate()->find(1);
                DB::table('system_settings')->where('id', 1)->update($paths + [
                    'app_name' => ($data['reset_name'] ?? false) ? Brand::NAME : $data['app_name'],
                    'updated_at' => now(),
                ]);

                return array_intersect_key((array) $settings, $paths);
            }, 3);
        } catch (Throwable $error) {
            $this->deleteFiles($paths);
            if ($error instanceof RuntimeException && ! $error instanceof HttpExceptionInterface) {
                report($error);
                throw ValidationException::withMessages(['app_name' => __('Identitas gagal disimpan. Coba lagi.')]);
            }
            throw $error;
        }

        $this->deleteFiles($retired);

        return redirect()->route('identity.index')->with('status', __('Identitas disimpan.'));
    }

    public function asset(string $kind, string $filename)
    {
        $path = 'branding/'.$filename;
        $settings = DB::table('system_settings')->find(1);
        $disk = Storage::disk('local');
        abort_unless(($settings->{$kind.'_path'} ?? null) === $path && $disk->exists($path), 404);
        $mime = str_ends_with($path, '.webp') ? 'image/webp' : 'image/png';

        return $disk->response($path, null, [
            'Content-Type' => $mime,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    private function storeImage(UploadedFile $upload, string $kind): string
    {
        $bytes = $upload->get();
        $info = @getimagesizefromstring($bytes);
        $type = $info[2] ?? null;
        Ledger::check($info && $info[0] * $info[1] <= 4_194_304 &&
            ($type === IMAGETYPE_PNG || ($kind === 'logo' && $type === IMAGETYPE_WEBP)),
            $kind, 'Pilih gambar valid dengan maksimal 4.194.304 piksel.');
        Ledger::check(function_exists('imagecreatefromstring'), $kind, 'Pemrosesan gambar sedang tidak tersedia.');
        $image = @imagecreatefromstring($bytes);
        Ledger::check($image !== false, $kind, 'Berkas gambar tidak dapat dibaca.');

        $extension = $type === IMAGETYPE_WEBP ? 'webp' : 'png';
        $path = 'branding/'.Str::uuid().'.'.$extension;
        $contents = tmpfile();
        if ($contents === false) {
            throw new RuntimeException('Cannot open temporary branding stream.');
        }

        try {
            imagesavealpha($image, true);
            $encoded = $extension === 'webp' ? imagewebp($image, $contents, 90) : imagepng($image, $contents);
            if (! $encoded || ftell($contents) === 0 || ! rewind($contents) || ! Storage::disk('local')->put($path, $contents)) {
                throw new RuntimeException('Cannot write branding image.');
            }
        } catch (Throwable $error) {
            $this->deleteFiles([$path]);
            throw $error;
        } finally {
            if (is_resource($contents)) {
                fclose($contents);
            }
        }

        return $path;
    }

    private function deleteFiles(array $paths): void
    {
        foreach (array_filter($paths) as $path) {
            try {
                Storage::disk('local')->delete($path);
            } catch (Throwable $error) {
                report($error);
            }
        }
    }
}
