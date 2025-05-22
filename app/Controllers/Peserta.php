<?php
namespace App\Controllers;

use App\Models\ExamModel;
use App\Models\QuestionModel;
use App\Models\ResultModel;
use CodeIgniter\Exceptions\PageNotFoundException;
use Dompdf\Dompdf;
use App\Libraries\SpreadsheetWriter;

class Peserta extends BaseController
{
    public function dashboard()
    {
        $session = session();
        
        // Check if user is logged in and is a participant/peserta
        if (!$session->has('user_id') || 
            ($session->get('user_role') !== 'peserta' && $session->get('user_role') !== 'participant')) {
            return redirect()->to('/login')->with('error', 'Please login as a participant to access this page');
        }

        $userId = $session->get('user_id');
        $examModel = new ExamModel();
        $resultModel = new ResultModel();

        // Only get published exams
        $exams = $examModel->where('exam_stat', 'published')->findAll();

        $filteredExams = [];
        foreach ($exams as $exam) {
            $alreadyTaken = $resultModel
                ->where('exam_id', $exam['id'])
                ->where('user_id', $userId)
                ->first();

            if (!$alreadyTaken) {
                $filteredExams[] = $exam;
            }
        }

        // Get completed exams count
        $completedExams = $resultModel
            ->where('user_id', $userId)
            ->countAllResults();

        // Get highest score
        $highestScore = $resultModel
            ->where('user_id', $userId)
            ->selectMax('score')
            ->first();

        return view('peserta/dashboard', [
            'session' => $session,
            'exams' => $filteredExams,
            'completedExams' => $completedExams,
            'highestScore' => $highestScore ? $highestScore['score'] : 0
        ]);
    }


    public function startExam($examId)
    {
        $examModel = new ExamModel();
        $questionModel = new QuestionModel();

        $exam = $examModel->find($examId);
        if (!$exam) {
            throw PageNotFoundException::forPageNotFound();
        }

        $questions = $questionModel->where('exam_id', $examId)->findAll();

        return view('peserta/exam_view', [
            'exam' => $exam,
            'questions' => $questions
        ]);
    }

    public function exam($id)
    {
        $examModel = new ExamModel();
        $questionModel = new QuestionModel();

        // Only allow access to published exams
        $exam = $examModel->where('exam_stat', 'published')->find($id);
        if (!$exam) {
            throw PageNotFoundException::forPageNotFound('Ujian tidak ditemukan atau belum dipublikasi.');
        }

        $questions = $questionModel->where('exam_id', $id)->findAll();

        return view('peserta/exam', [
            'exam' => $exam,
            'questions' => $questions
        ]);
    }

    public function submitExam()
    {

        $questionModel = new QuestionModel();
        $resultModel = new ResultModel();

        $examId = $this->request->getPost('exam_id');
        $answers = $this->request->getPost('answers') ?? [];

        $allQuestions = $questionModel->where('exam_id', $examId)->findAll();

        $correctCount = 0;

        $user = (new \App\Models\UserModel())->find(session()->get('user_id'));


        foreach ($allQuestions as $question) {
            $questionId = $question['id'];
            $selectedOption = $answers[$questionId] ?? null; // null jika tidak dijawab

            if ($selectedOption && strtoupper($selectedOption) === strtoupper($question['correct_option'])) {
                $correctCount++;
            }
        }

        $totalQuestions = count($allQuestions);
        $score = round(($correctCount / $totalQuestions) * 100);

        $resultModel->insert([
            'exam_id' => $examId,
            'user_id' => session()->get('user_id'),
            'score' => $score,
            'taken_at' => date('Y-m-d H:i:s')
        ]);
        $spreadsheet = new SpreadsheetWriter();
        $spreadsheet->appendRow([
            $user['name'],
            (new ExamModel())->find($examId)['title'],
            round($score),
            date('Y-m-d H:i:s')
        ]);

        return view('peserta/result', [
            'examTitle' => (new ExamModel())->find($examId)['title'],
            'score' => $score,
            'correctCount' => $correctCount,
            'totalQuestions' => $totalQuestions,
        ]);
    }


    public function result($examId)
    {
        $resultModel = new ResultModel();
        $session = session();
        $userId = $session->get('user_id');

        $data['results'] = $resultModel->where('exam_id', $examId)
            ->where('user_id', $userId)
            ->findAll();

        return view('peserta/result', $data);
    }
    public function history()
    {
        $resultModel = new ResultModel();
        $examModel = new ExamModel();
        $userId = session()->get('user_id');

        $results = $resultModel->where('user_id', $userId)->findAll();

        foreach ($results as &$result) {
            $exam = $examModel->find($result['exam_id']);
            $result['exam_title'] = $exam ? $exam['title'] : 'Ujian Tidak Ditemukan';
        }

        return view('peserta/history', [
            'results' => $results
        ]);
    }

    public function downloadHistoryPdf()
    {
        // Increase memory limit and set time limit
        ini_set('memory_limit', '512M');
        set_time_limit(300); // 5 minutes

        // Get user data and exam history
        $userId = session()->get('user_id');
        $userModel = new \App\Models\UserModel();
        $resultModel = new ResultModel();
        $examModel = new ExamModel();

        $user = $userModel->find($userId);
        $results = $resultModel->where('user_id', $userId)
            ->orderBy('taken_at', 'DESC')
            ->limit(50)
            ->findAll();

        // Process exam results
        foreach ($results as &$result) {
            $exam = $examModel->find($result['exam_id']);
            $result['exam_title'] = $exam ? $exam['title'] : 'Ujian Tidak Ditemukan';
        }

        // Calculate statistics
        $stats = $this->calculateExamStatistics($results);

        // Generate PDF content
        $html = $this->generatePdfHtml($user, $results, $stats);

        try {
            // Configure PDF options
            $options = new \Dompdf\Options();
            $options->set([
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => false,
                'defaultFont' => 'dejavusans',
                'isPhpEnabled' => false,
                'debugKeepTemp' => false,
                'debugCss' => false,
                'debugLayout' => false,
                'chroot' => FCPATH,
                'defaultMediaType' => 'screen',
                'fontCache' => WRITEPATH . 'fonts/',
                'tempDir' => WRITEPATH . 'temp/'
            ]);

            // Create and configure PDF
            $dompdf = new \Dompdf\Dompdf($options);
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();

            // Stream PDF for download
            $filename = 'Transkrip_Akademik_' . $user['name'] . '_' . date('Ymd_His') . '.pdf';
            $dompdf->stream($filename, ['Attachment' => true]);

            // Clean up
            $this->cleanup($dompdf, $html, $results);

        } catch (\Exception $e) {
            log_message('error', 'PDF Generation Error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Gagal membuat PDF. Silakan coba lagi nanti.');
        }
    }

    private function calculateExamStatistics($results)
    {
        if (empty($results)) {
            return [
                'totalExams' => 0,
                'avgScore' => 0,
                'highestScore' => 0,
                'lowestScore' => 0,
                'recentScore' => 0
            ];
        }

        $scores = array_column($results, 'score');
        return [
            'totalExams' => count($results),
            'avgScore' => round(array_sum($scores) / count($scores), 1),
            'highestScore' => max($scores),
            'lowestScore' => min($scores),
            'recentScore' => $results[0]['score'] // Most recent score
        ];
    }

    private function getPdfStyles()
    {
        return '
        @page {
            margin: 0;
            padding: 0;
        }
        
        body {
            font-family: dejavusans, sans-serif;
            line-height: 1.6;
            color: #000000;
            margin: 0;
            padding: 0;
            background: #FFFFFF;
        }

        .page-break {
            page-break-before: always;
        }

        .no-break {
            page-break-inside: avoid;
        }

        .header {
            background: #003366;
            color: white;
            padding: 2.5em 3em;
            position: relative;
            overflow: hidden;
            page-break-after: avoid;
        }

        .logo-container {
            display: flex;
            align-items: center;
            margin-bottom: 1.5em;
            position: relative;
            z-index: 2;
            background: rgba(255, 255, 255, 0.1);
            padding: 1em;
            border-radius: 10px;
        }

        .logo {
            width: 80px;
            height: auto;
            margin-right: 1.5em;
            background: white;
            padding: 10px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }

        .header-text {
            border-left: 4px solid #FFFFFF;
            padding-left: 1.5em;
        }

        .institution {
            font-size: 32px;
            margin: 0;
            font-weight: bold;
            letter-spacing: 0.5px;
            color: #FFFFFF;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }

        .subtitle {
            margin: 0.3em 0 0;
            font-size: 18px;
            letter-spacing: 0.5px;
            color: #FFFFFF;
        }

        .document-info {
            margin-top: 2em;
            padding-top: 1.5em;
            border-top: 2px solid #FFFFFF;
        }

        .document-title {
            font-size: 36px;
            margin: 0;
            font-weight: bold;
            letter-spacing: 0.5px;
            color: #FFFFFF;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }

        .document-subtitle {
            margin: 0.5em 0 0;
            font-size: 20px;
            color: #FFFFFF;
        }

        .content {
            padding: 3em;
            background: white;
            position: relative;
            margin-top: -2em;
            border-radius: 15px 15px 0 0;
            box-shadow: 0 -5px 20px rgba(0,0,0,0.2);
        }

        .section {
            margin-bottom: 2.5em;
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            border: 2px solid #003366;
            overflow: hidden;
        }

        .section-header {
            background: #003366;
            padding: 1.2em 1.5em;
            border-bottom: 3px solid #002347;
        }

        .section-header h3 {
            margin: 0;
            color: white;
            font-size: 1.25em;
            font-weight: 600;
            display: flex;
            align-items: center;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.3);
        }

        .section-header h3::before {
            content: "";
            display: inline-block;
            width: 4px;
            height: 1em;
            background: #FFFFFF;
            margin-right: 0.8em;
            border-radius: 2px;
            box-shadow: 1px 1px 2px rgba(0,0,0,0.2);
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1.5em;
            padding: 1.5em;
            background: #F0F8FF;
        }

        .info-item {
            background: white;
            padding: 1.2em;
            border-radius: 8px;
            border: 2px solid #003366;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .label {
            color: #003366;
            font-size: 0.875em;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0.5em;
        }

        .value {
            color: #000000;
            font-size: 1.1em;
            font-weight: 600;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1.2em;
            padding: 1.5em;
            background: #F0F8FF;
        }

        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 1.5em 1.2em;
            text-align: center;
            border: 2px solid #003366;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 16px rgba(0,0,0,0.2);
            border-color: #0066CC;
        }

        .stat-value {
            font-size: 32px;
            font-weight: bold;
            color: #003366;
            margin-bottom: 0.3em;
            text-shadow: 1px 1px 1px rgba(0,0,0,0.1);
        }

        .stat-label {
            color: #000000;
            font-size: 0.875em;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .history-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin: 1em 0;
        }

        .history-table th,
        .history-table td {
            padding: 1em 1.2em;
            text-align: left;
            border: 1px solid #003366;
        }

        .history-table th {
            background: #003366;
            font-weight: 600;
            color: white;
            font-size: 0.95em;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 3px solid #002347;
        }

        .history-table tr:nth-child(even) {
            background: #F0F8FF;
        }

        .history-table tr:hover {
            background: #E6F3FF;
        }

        .badge {
            display: inline-block;
            padding: 0.5em 1em;
            border-radius: 20px;
            font-size: 0.875em;
            font-weight: 700;
            text-align: center;
            min-width: 120px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            border: 2px solid;
        }

        .badge-excellent {
            background: #00CC66;
            color: #FFFFFF;
            border-color: #009933;
        }

        .badge-good {
            background: #3399FF;
            color: #FFFFFF;
            border-color: #0066CC;
        }

        .badge-average {
            background: #FFCC00;
            color: #000000;
            border-color: #CC9900;
        }

        .badge-poor {
            background: #FF3333;
            color: #FFFFFF;
            border-color: #CC0000;
        }

        .signature-section {
            margin: 4em 0 2em;
            text-align: right;
            padding-right: 2em;
        }

        .signature-box {
            display: inline-block;
            text-align: center;
            padding: 2em;
            background: #F0F8FF;
            border-radius: 8px;
            border: 2px solid #003366;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .signature-date {
            color: #000000;
            margin-bottom: 0.5em;
            font-weight: 600;
        }

        .signature-title {
            color: #003366;
            font-size: 0.9em;
            margin-bottom: 2em;
            font-weight: 600;
        }

        .signature-line {
            width: 200px;
            border-bottom: 2px solid #003366;
            margin: 0 auto 1em;
        }

        .signature-name {
            font-weight: 700;
            color: #000000;
            font-size: 1.1em;
        }

        .footer {
            background: #003366;
            color: #FFFFFF;
            padding: 2em;
            text-align: center;
            position: relative;
            margin-top: 3em;
        }

        .footer::before {
            content: "";
            position: absolute;
            top: -20px;
            left: 0;
            right: 0;
            height: 20px;
            background: linear-gradient(to bottom right, transparent 49%, #003366 50%);
        }

        .footer-text {
            margin-bottom: 1em;
            font-size: 0.95em;
            color: #FFFFFF;
            font-weight: 500;
        }

        .document-id {
            font-family: monospace;
            background: rgba(255,255,255,0.15);
            padding: 0.8em 1.5em;
            border-radius: 6px;
            display: inline-block;
            font-size: 0.95em;
            letter-spacing: 1px;
            border: 2px solid rgba(255,255,255,0.3);
            color: #FFFFFF;
            font-weight: 600;
        }

        .empty-message {
            text-align: center;
            color: #003366;
            font-style: italic;
            padding: 3em;
            background: #F0F8FF;
            border-radius: 8px;
            margin: 1em;
            border: 2px dashed #003366;
            font-weight: 500;
        }

        .score {
            font-weight: 700;
            font-size: 1.2em;
            color: #003366;
        }';
    }

    private function generatePdfHtml($user, $results, $stats)
    {
        $css = $this->getPdfStyles();
        
        $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Transkrip Akademik</title>
    <style>' . $css . '</style>
</head>
<body>
    <!-- First Page -->
    <div class="no-break">
        <div class="header">
            <div class="logo-container">
                <img src="' . FCPATH . 'assets/images/logo.png" class="logo" alt="Logo">
                <div class="header-text">
                    <h1 class="institution">EXAMINATION</h1>
                    <p class="subtitle">Platform Ujian Online Terpercaya</p>
                </div>
            </div>
            <div class="document-info">
                <h2 class="document-title">Transkrip Akademik</h2>
                <p class="document-subtitle">Laporan Hasil Pembelajaran & Pencapaian</p>
            </div>
        </div>

        <div class="content">
            <!-- Student Information Section -->
            <div class="section no-break">
                <div class="section-header">
                    <h3>Informasi Peserta</h3>
                </div>
                <div class="info-grid">
                    <div class="info-item">
                        <div class="label">Nama Lengkap</div>
                        <div class="value">' . esc($user['name']) . '</div>
                    </div>
                    <div class="info-item">
                        <div class="label">Email</div>
                        <div class="value">' . esc($user['email']) . '</div>
                    </div>
                    <div class="info-item">
                        <div class="label">ID Peserta</div>
                        <div class="value">' . str_pad($user['id'], 5, '0', STR_PAD_LEFT) . '</div>
                    </div>
                    <div class="info-item">
                        <div class="label">Tanggal Cetak</div>
                        <div class="value">' . date('d F Y H:i') . ' WIB</div>
                    </div>
                </div>
            </div>

            <!-- Performance Summary Section -->
            <div class="section no-break">
                <div class="section-header">
                    <h3>Ringkasan Performa</h3>
                </div>
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-value">' . $stats['totalExams'] . '</div>
                        <div class="stat-label">Total Ujian</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value">' . $stats['avgScore'] . '</div>
                        <div class="stat-label">Rata-rata Nilai</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value">' . $stats['highestScore'] . '</div>
                        <div class="stat-label">Nilai Tertinggi</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value">' . $stats['recentScore'] . '</div>
                        <div class="stat-label">Nilai Terakhir</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Second Page - Exam History -->
    <div class="page-break"></div>
    <div class="no-break">
        <div class="header" style="padding: 1.5em 3em;">
            <div class="logo-container" style="margin-bottom: 0;">
                <img src="' . FCPATH . 'assets/images/logo.png" class="logo" alt="Logo" style="width: 60px;">
                <div class="header-text">
                    <h1 class="institution" style="font-size: 24px;">EXAMINATION</h1>
                </div>
            </div>
        </div>

        <div class="content">
            <div class="section no-break">
                <div class="section-header">
                    <h3>Riwayat Ujian</h3>
                </div>
                <table class="history-table">
                    <thead>
                        <tr>
                            <th style="width: 5%">No.</th>
                            <th style="width: 40%">Nama Ujian</th>
                            <th style="width: 15%">Nilai</th>
                            <th style="width: 20%">Kategori</th>
                            <th style="width: 20%">Tanggal</th>
                        </tr>
                    </thead>
                    <tbody>';

        if (!empty($results)) {
            $no = 1;
            foreach ($results as $result) {
                $scoreCategory = $this->getScoreCategory($result['score']);
                $html .= '
                        <tr>
                            <td>' . $no++ . '</td>
                            <td>' . esc($result['exam_title']) . '</td>
                            <td class="score">' . $result['score'] . '</td>
                            <td><span class="badge ' . $scoreCategory['class'] . '">' . $scoreCategory['label'] . '</span></td>
                            <td>' . date('d/m/Y H:i', strtotime($result['taken_at'])) . '</td>
                        </tr>';

                // Add page break every 10 items
                if ($no % 10 === 0 && $no !== count($results)) {
                    $html .= '
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="page-break"></div>
    <div class="no-break">
        <div class="header" style="padding: 1.5em 3em;">
            <div class="logo-container" style="margin-bottom: 0;">
                <img src="' . FCPATH . 'assets/images/logo.png" class="logo" alt="Logo" style="width: 60px;">
                <div class="header-text">
                    <h1 class="institution" style="font-size: 24px;">EXAMINATION</h1>
                </div>
            </div>
        </div>
        <div class="content">
            <div class="section no-break">
                <div class="section-header">
                    <h3>Riwayat Ujian (Lanjutan)</h3>
                </div>
                <table class="history-table">
                    <thead>
                        <tr>
                            <th style="width: 5%">No.</th>
                            <th style="width: 40%">Nama Ujian</th>
                            <th style="width: 15%">Nilai</th>
                            <th style="width: 20%">Kategori</th>
                            <th style="width: 20%">Tanggal</th>
                        </tr>
                    </thead>
                    <tbody>';
                }
            }
        } else {
            $html .= '<tr><td colspan="5"><div class="empty-message">Belum ada riwayat ujian</div></td></tr>';
        }

        $html .= '
                    </tbody>
                </table>
            </div>

            <div class="signature-section no-break">
                <div class="signature-box">
                    <p class="signature-date">Jakarta, ' . date('d F Y') . '</p>
                    <p class="signature-title">Kepala Akademik</p>
                    <div class="signature-line"></div>
                    <p class="signature-name">Admin ExamNation</p>
                </div>
            </div>
        </div>
    </div>

    <div class="footer">
        <p class="footer-text">Dokumen ini diterbitkan secara digital oleh ExamNation</p>
        <p class="footer-text">Verifikasi keaslian dokumen dapat dilakukan melalui website ExamNation</p>
        <div class="document-id">ID: EXNTN-' . time() . '-' . str_pad($user['id'], 4, '0', STR_PAD_LEFT) . '</div>
    </div>
</body>
</html>';

        return $html;
    }

    private function getScoreCategory($score)
    {
        if ($score >= 85) {
            return ['class' => 'badge-excellent', 'label' => 'Sangat Baik'];
        } elseif ($score >= 75) {
            return ['class' => 'badge-good', 'label' => 'Baik'];
        } elseif ($score >= 60) {
            return ['class' => 'badge-average', 'label' => 'Cukup'];
        } else {
            return ['class' => 'badge-poor', 'label' => 'Perlu Perbaikan'];
        }
    }

    private function cleanup(&$dompdf, &$html, &$results)
    {
        $dompdf = null;
        $html = null;
        $results = null;
        unset($dompdf, $html, $results);
        gc_collect_cycles();
    }

    public function showResult()
    {
        return view('peserta/result');
    }
}