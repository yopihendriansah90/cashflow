<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

abstract class Controller
{
    protected function validateCashflow(Request $request, array $rules, array $messages = []): array
    {
        return $request->validate($rules, $messages + [
            'required' => __('Kolom ini wajib diisi.'),
            'email' => __('Masukkan alamat email yang valid.'),
            'string' => __('Masukkan teks yang valid.'),
            'integer' => __('Masukkan bilangan bulat yang valid.'),
            'amount.regex' => __('Masukkan nominal rupiah, misalnya 1250000 atau 1.250.000.'),
            'fee_amount.regex' => __('Masukkan nominal rupiah, misalnya 1250000 atau 1.250.000.'),
            'opening_balance.regex' => __('Masukkan nominal rupiah, misalnya 1250000 atau 1.250.000.'),
            'date_format' => __('Masukkan tanggal yang valid.'),
            'after_or_equal' => __('Tanggal berada di luar rentang yang didukung.'),
            'in' => __('Pilih salah satu opsi yang tersedia.'),
            'required_unless' => __('Kolom ini wajib diisi.'),
            'amount.min' => __('Nominal harus lebih besar dari nol.'),
            'fee_amount.min' => __('Biaya tidak boleh negatif.'),
            'file' => __('Pilih berkas yang valid.'),
            'uploaded' => __('Berkas gagal diunggah.'),
            'max' => __('Isian ini terlalu panjang.'),
            'password.min' => __('Kata sandi minimal 8 karakter.'),
            'password.confirmed' => __('Kata sandi dan ulangannya harus sama.'),
            'password.regex' => __('Kata sandi maksimal 72 byte dan tidak boleh berisi karakter NUL.'),
        ]);
    }
}
