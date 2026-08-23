<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class ApiClientDemo extends Command
{
    /**
     * Contoh klien REST API Simple POS (minggu 10 — konsumsi API dari sisi klien).
     *
     * Perintah ini BUKAN bagian dari server; ia berpura-pura menjadi aplikasi kasir
     * mobile yang memanggil API dari luar: login untuk mendapatkan token, memakai
     * token itu pada setiap request terproteksi, dan memeriksa status respons sebelum
     * mempercayai isinya, alih-alih mengasumsikan setiap panggilan selalu berhasil.
     */
    protected $signature = 'api:demo {--base-url=http://127.0.0.1:8000}';

    protected $description = 'Contoh klien yang mengonsumsi REST API Simple POS: login, ambil produk, buat transaksi.';

    public function handle(): int
    {
        $baseUrl = rtrim((string) $this->option('base-url'), '/');

        // 1. Login: kirim kredensial, terima token. Kredensial di sini adalah akun
        //    demo kasir dari seeder; pada klien sungguhan, ini datang dari form login.
        $login = Http::post("{$baseUrl}/api/login", [
            'email' => 'kasir@pos.test',
            'password' => 'password',
        ]);

        if ($login->failed()) {
            $this->error("Login gagal ({$login->status()}): ".$login->json('message', $login->body()));

            return self::FAILURE;
        }

        $token = $login->json('token');
        $this->info("Login berhasil. Token: {$token}");

        // 2. Panggil endpoint terproteksi lewat withToken(); token dikirim ulang
        //    sebagai header Authorization: Bearer pada setiap request berikutnya.
        $products = Http::withToken($token)->get("{$baseUrl}/api/products");

        if ($products->failed()) {
            $this->error("Gagal mengambil daftar produk ({$products->status()}).");

            return self::FAILURE;
        }

        $firstProduct = $products->json('data.0');

        if (! $firstProduct) {
            $this->warn('Tidak ada produk aktif untuk didemokan. Jalankan seeder terlebih dahulu.');

            return self::FAILURE;
        }

        $this->info("Produk pertama: {$firstProduct['name']} (Rp {$firstProduct['price']}, stok {$firstProduct['stock']}).");

        // 3. Kirim transaksi demo. Server tetap menghitung ulang total dari harga
        //    produk yang tersimpan (Bab 6); klien hanya mengirim product_id dan qty.
        $transaction = Http::withToken($token)->post("{$baseUrl}/api/transactions", [
            'items' => [
                ['product_id' => $firstProduct['id'], 'qty' => 1],
            ],
        ]);

        if ($transaction->failed()) {
            $this->error("Transaksi gagal ({$transaction->status()}): ".json_encode($transaction->json()));

            return self::FAILURE;
        }

        $this->info('Transaksi berhasil dibuat:');
        $this->line(json_encode($transaction->json('data'), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return self::SUCCESS;
    }
}
