<?php

namespace App\Http\Controllers;

use App\Models\AccurateDatabase;
use Illuminate\Http\Request;
use App\Services\AccurateService;
use Illuminate\Support\Facades\Log;
use Exception;

class DatabaseSelectionController extends Controller
{
  public function showSelection(AccurateService $accurate)
  {
    try {
      $databases = $accurate->getDatabaseList();

      if (count($databases) === 1) {
        // Memanggil method openDatabaseById untuk mendapatkan host dan session
        $dbDetail = $accurate->openDatabaseById($databases[0]['id']);
        if ($dbDetail) {
          session(['accurate_database' => $dbDetail]);
          return redirect()->route('login.redirect')->with('success', 'Database Accurate berhasil terhubung secara otomatis.');
        }
      }

      return view('database.selection', ['databases' => $databases]);
    } catch (Exception $e) {
      session()->forget('accurate_access_token');
      return redirect()->route('accurate.auth')->with('info', 'Sesi Accurate Anda telah berakhir, silakan otorisasi ulang.');
    }
  }


  public function selectDatabase(Request $request, AccurateService $accurate)
  {
    $request->validate(['selected_db_json' => 'required|json']);
    $dbData = json_decode($request->input('selected_db_json'), true);
    try {
      $detailDb = $accurate->openDatabaseById($dbData['id']);

      // if (!$detailDb || !isset($detailDb['session'])) {
      //   return $this->handleRedirect($request, 'error', 'Gagal mendapatkan sesi untuk database yang dipilih.');
      // }

      session([
        'accurate_database' => $detailDb,
        'database_id' => $dbData['id'],
        'database_name' => $dbData['alias'],
      ]);
      return $this->handleRedirect($request, 'success', 'Successfully connected to ' . $dbData['alias']);
    } catch (Exception $e) {
      Log::error('DB_SELECTION_ERROR', ['message' => $e->getMessage()]);
      return $this->handleRedirect($request, 'error', 'Terjadi kesalahan saat memilih database: ' . $e->getMessage());
    }
  }

  private function handleRedirect(Request $request, string $type, string $message)
  {
    $previousUrl = url()->previous();

    // Jika berasal dari halaman /select-database, redirect ke route modules.index
    if (str_contains($previousUrl, '/select-database')) {
      return redirect()->route('admin.dashboard')->with($type, $message);
    }

    // Selain itu, redirect back ke halaman sebelumnya
    return redirect()->back()->with($type, $message);
  }
}
