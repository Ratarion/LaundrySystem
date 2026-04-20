<?php
namespace App\Controllers;

use Models\Booking;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;

class StatsController extends BaseController
{
    public function index()
    {
        if (($_SESSION['role'] ?? 0) !== 1) {
            $this->redirect('/booking');
        }

        $this->log->info('Открыта страница Статистика (Админ)', ['ip' => $_SERVER['REMOTE_ADDR']]);

        $from = $_POST['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
        $to   = $_POST['date_to']   ?? date('Y-m-d');

        $data = $this->getReportData($from, $to);

        $this->render('stats', $data);
    }

    /**
     * Экспорт в Excel
     */
    public function exportXlsx()
    {
        if (($_SESSION['role'] ?? 0) !== 1) {
            $this->redirect('/booking');
        }

        $from = $_GET['from'] ?? date('Y-m-d', strtotime('-30 days'));
        $to   = $_GET['to']   ?? date('Y-m-d');

        $data = $this->getReportData($from, $to);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Заголовок
        $sheet->setCellValue('A1', 'Отчёт по бронированиям');
        $sheet->setCellValue('A2', "Период: {$from} — {$to}");
        $sheet->mergeCells('A1:E1');

        // Основные показатели
        $sheet->setCellValue('A4', 'Всего бронирований');
        $sheet->setCellValue('B4', $data['totalBookings']);
        $sheet->setCellValue('A5', 'Активных');
        $sheet->setCellValue('B5', $data['activeCount']);
        $sheet->setCellValue('A6', 'Отменено');
        $sheet->setCellValue('B6', $data['cancelledCount']);

        // Топ-5 машин
        $sheet->setCellValue('A8', 'Топ-5 машин');
        $row = 9;
        foreach ($data['topMachines'] as $machine => $cnt) {
            $sheet->setCellValue('A' . $row, $machine);
            $sheet->setCellValue('B' . $row, $cnt);
            $row++;
        }

        // Таблица бронирований
        $sheet->setCellValue('A' . ($row + 2), 'Список бронирований');
        $headers = ['ID', 'Житель', 'Комната', 'Машина', 'Начало', 'Конец', 'Статус'];
        $col = 'A';
        foreach ($headers as $h) {
            $sheet->setCellValue($col . ($row + 3), $h);
            $col++;
        }

        $row += 4;
        foreach ($data['bookings'] as $b) {
            $sheet->setCellValue('A' . $row, $b['id']);
            $sheet->setCellValue('B' . $row, $b['last_name'] . ' ' . $b['first_name']);
            $sheet->setCellValue('C' . $row, $b['inidroom']);
            $sheet->setCellValue('D' . $row, $b['type_machine'] . ' #' . $b['number_machine']);
            $sheet->setCellValue('E' . $row, $b['start_time']);
            $sheet->setCellValue('F' . $row, $b['end_time']);
            $sheet->setCellValue('G' . $row, $b['status']);
            $row++;
        }

        $writer = new Xlsx($spreadsheet);
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="report_' . date('Y-m-d_H-i') . '.xlsx"');
        $writer->save('php://output');
        exit;
    }

    /**
     * Экспорт в Word
     */
    public function exportDocx()
    {
        if (($_SESSION['role'] ?? 0) !== 1) {
            $this->redirect('/booking');
        }

        $from = $_GET['from'] ?? date('Y-m-d', strtotime('-30 days'));
        $to   = $_GET['to']   ?? date('Y-m-d');

        $data = $this->getReportData($from, $to);

        $phpWord = new PhpWord();
        $section = $phpWord->addSection();

        $section->addText("Отчёт по бронированиям", ['bold' => true, 'size' => 16]);
        $section->addText("Период: {$from} — {$to}");

        $section->addTextBreak(1);
        $section->addText("Всего бронирований: {$data['totalBookings']}");
        $section->addText("Активных: {$data['activeCount']}");
        $section->addText("Отменено: {$data['cancelledCount']} ({$data['cancelledPercent']}%)");

        $section->addTextBreak(1);
        $section->addText("Топ-5 самых загруженных машин:", ['bold' => true]);
        $table = $section->addTable();
        $table->addRow();
        $table->addCell(4000)->addText('Машина');
        $table->addCell(2000)->addText('Бронирований');
        foreach ($data['topMachines'] as $machine => $cnt) {
            $table->addRow();
            $table->addCell(4000)->addText($machine);
            $table->addCell(2000)->addText($cnt);
        }

        $section->addTextBreak(1);
        $section->addText("Список бронирований:", ['bold' => true]);

        $table = $section->addTable(['borderSize' => 6, 'borderColor' => '999999']);
        $table->addRow();
        $headers = ['ID', 'Житель', 'Комната', 'Машина', 'Начало', 'Конец', 'Статус'];
        foreach ($headers as $h) $table->addCell(1500)->addText($h);

        foreach ($data['bookings'] as $b) {
            $table->addRow();
            $table->addCell(1500)->addText($b['id']);
            $table->addCell(3000)->addText($b['last_name'] . ' ' . $b['first_name']);
            $table->addCell(1500)->addText($b['inidroom']);
            $table->addCell(2500)->addText($b['type_machine'] . ' #' . $b['number_machine']);
            $table->addCell(2000)->addText($b['start_time']);
            $table->addCell(2000)->addText($b['end_time']);
            $table->addCell(1500)->addText($b['status']);
        }

        header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        header('Content-Disposition: attachment; filename="report_' . date('Y-m-d_H-i') . '.docx"');
        $writer = IOFactory::createWriter($phpWord, 'Word2007');
        $writer->save('php://output');
        exit;
    }

    /**
     * Общая логика получения данных отчёта
     */
    private function getReportData($from, $to)
    {
        $bookingsData = Booking::getAll($this->pdo, $from, $to);

        $totalBookings = count($bookingsData);
        $cancelledCount = 0;
        $activeCount = 0;
        $dailyData = [];
        $topMachines = [];

        foreach ($bookingsData as $b) {
            $status = $b['status'] ?? '';
            if (in_array($status, ['cancelled', 'Отменено', 'Отмена'])) {
                $cancelledCount++;
            } else {
                $activeCount++;
            }

            $day = substr($b['start_time'], 0, 10);
            $dailyData[$day] = ($dailyData[$day] ?? 0) + 1;

            $machineKey = $b['type_machine'] . ' #' . $b['number_machine'];
            $topMachines[$machineKey] = ($topMachines[$machineKey] ?? 0) + 1;
        }

        arsort($topMachines);
        $topMachines = array_slice($topMachines, 0, 5, true);

        return [
            'totalBookings'  => $totalBookings,
            'activeCount'    => $activeCount,
            'cancelledCount' => $cancelledCount,
            'cancelledPercent' => $totalBookings ? round($cancelledCount / $totalBookings * 100) : 0,
            'topMachines'    => $topMachines,
            'dailyLabels'    => array_keys($dailyData),
            'dailyCounts'    => array_values($dailyData),
            'bookings'       => $bookingsData,   // для таблиц в отчётах
            'from'           => $from,
            'to'             => $to
        ];
    }
}