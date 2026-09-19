<?php
namespace App\Controllers;

use Models\Booking;
use Models\Dormitory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;

class StatsController extends BaseController
{
    public function index()
    {
        if (!isset($_SESSION['admin_id'])) {
            $this->redirect('/login?error=' . urlencode('Доступ запрещён. Пожалуйста, войдите в систему.'));
        }

        if (($_SESSION['role'] ?? 0) !== 1) {
            $this->redirect('/booking?error=' . urlencode('Недостаточно прав для просмотра статистики.'));
        }

        $this->log->info('Открыта страница Статистика (Админ)', ['ip' => $_SERVER['REMOTE_ADDR']]);

        $from = $_POST['date_from'] ?? ($_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days')));
        $to   = $_POST['date_to']   ?? ($_GET['date_to']   ?? date('Y-m-d'));

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
            $from = date('Y-m-d', strtotime('-30 days'));
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
            $to = date('Y-m-d');
        }

        $sessionDormId = !empty($_SESSION['dormitory_id']) ? (int)$_SESSION['dormitory_id'] : null;
        $role = $_SESSION['role'] ?? 0;
        $roleName = getUserRoleTitle($role, $sessionDormId, $_SESSION['dormitory_name'] ?? null);

        $dormitory_id = $_POST['dormitory_id'] ?? ($_GET['dormitory_id'] ?? '');
        if ($sessionDormId !== null) {
            $dormitory_id = (string)$sessionDormId;
        }

        $dormitories = Dormitory::getAll($this->pdo);
        $selectedDormName = $this->resolveDormitoryName($dormitories, $dormitory_id);

        $data = $this->getReportData($from, $to, $dormitory_id);
        $data['roleName']         = $roleName;
        $data['sessionDormId']    = $sessionDormId;
        $data['dormitories']      = $dormitories;
        $data['dormitory_id']     = $dormitory_id;
        $data['selectedDormName'] = $selectedDormName;

        $this->render('stats', $data);
    }

    /**
     * Экспорт в Excel
     */
    public function exportXlsx()
    {
        if (!isset($_SESSION['admin_id'])) {
            $this->redirect('/login?error=' . urlencode('Доступ запрещён. Пожалуйста, войдите в систему.'));
        }

        if (($_SESSION['role'] ?? 0) !== 1) {
            $this->redirect('/booking?error=' . urlencode('Недостаточно прав для просмотра статистики.'));
        }

        $from = $_GET['from'] ?? date('Y-m-d', strtotime('-30 days'));
        $to   = $_GET['to']   ?? date('Y-m-d');
        $sessionDormId = !empty($_SESSION['dormitory_id']) ? (int)$_SESSION['dormitory_id'] : null;
        $dormitory_id = $sessionDormId !== null ? $sessionDormId : (!empty($_GET['dormitory_id']) ? (int)$_GET['dormitory_id'] : null);

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
            $from = date('Y-m-d', strtotime('-30 days'));
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
            $to = date('Y-m-d');
        }

        $dormitories = Dormitory::getAll($this->pdo);
        $selectedDormName = $this->resolveDormitoryName($dormitories, $dormitory_id);

        $data = $this->getReportData($from, $to, $dormitory_id);

        $spreadsheet = new Spreadsheet();

        // ----------------- ЛИСТ 1: СВОДНЫЙ ОТЧЁТ И АНАЛИТИКА -----------------
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Сводка и Аналитика');

        // Шапка отчёта
        $sheet->setCellValue('A1', 'ОТЧЁТ ПО ИСПОЛЬЗОВАНИЮ ПРАЧЕЧНЫХ');
        $sheet->mergeCells('A1:G1');
        $sheet->getRowDimension(1)->setRowHeight(36);
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle('A1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1E293B');
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);

        $subTitle = "Период: {$from} — {$to}   |   Общежитие: {$selectedDormName}   |   Сформирован: " . date('d.m.Y H:i');
        $sheet->setCellValue('A2', $subTitle);
        $sheet->mergeCells('A2:G2');
        $sheet->getRowDimension(2)->setRowHeight(22);
        $sheet->getStyle('A2')->getFont()->setSize(10)->setItalic(true)->getColor()->setRGB('475569');
        $sheet->getStyle('A2')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F1F5F9');
        $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);

        // Ключевые показатели
        $sheet->setCellValue('A4', 'КЛЮЧЕВЫЕ ПОКАЗАТЕЛИ');
        $sheet->mergeCells('A4:C4');
        $sheet->getRowDimension(4)->setRowHeight(24);
        $sheet->getStyle('A4')->getFont()->setBold(true)->setSize(11)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle('A4')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('334155');
        $sheet->getStyle('A4')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT)->setVertical(Alignment::VERTICAL_CENTER);

        $kpiRows = [
            ['Всего бронирований', $data['totalBookings']],
            ['Активных (состоявшихся)', $data['activeCount']],
            ['Отменено', $data['cancelledCount'] . ' (' . $data['cancelledPercent'] . '%)'],
            ['Активных комнат', $data['uniqueRoomsCount']],
            ['Самая активная комната', $data['topRoomName'] . ' (' . $data['topRoomCount'] . ' стирок)'],
        ];

        $r = 5;
        foreach ($kpiRows as $kpi) {
            $sheet->setCellValue('A' . $r, $kpi[0]);
            $sheet->setCellValue('B' . $r, $kpi[1]);
            $sheet->mergeCells('B' . $r . ':C' . $r);
            $sheet->getStyle('A' . $r)->getFont()->setBold(true)->getColor()->setRGB('334155');
            $sheet->getStyle('A' . $r)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F8FAFC');
            $sheet->getStyle('B' . $r)->getFont()->setBold(true)->getColor()->setRGB('0F172A');
            $sheet->getStyle('B' . $r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $r++;
        }
        $sheet->getStyle('A4:C' . ($r - 1))->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('CBD5E1');

        // СТАТИСТИКА ПО КОМНАТАМ
        $r += 2;
        $sheet->setCellValue('A' . $r, 'СТАТИСТИКА ПО КОМНАТАМ');
        $sheet->mergeCells('A' . $r . ':G' . $r);
        $sheet->getRowDimension($r)->setRowHeight(26);
        $sheet->getStyle('A' . $r)->getFont()->setBold(true)->setSize(12)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle('A' . $r)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('4338CA');
        $sheet->getStyle('A' . $r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT)->setVertical(Alignment::VERTICAL_CENTER);

        $r++;
        $roomHeaders = ['№', 'Комната', 'Общежитие', 'Всего стирок', 'Активных', 'Отменено', '% отмен'];
        $colLetters = ['A', 'B', 'C', 'D', 'E', 'F', 'G'];
        $sheet->getRowDimension($r)->setRowHeight(24);
        foreach ($roomHeaders as $idx => $headerText) {
            $cell = $colLetters[$idx] . $r;
            $sheet->setCellValue($cell, $headerText);
            $sheet->getStyle($cell)->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
            $sheet->getStyle($cell)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('4F46E5');
            $sheet->getStyle($cell)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
        }

        $roomTableStart = $r + 1;
        $idx = 1;
        foreach ($data['roomStats'] as $roomItem) {
            $r++;
            $sheet->setCellValue('A' . $r, $idx++);
            $sheet->setCellValue('B' . $r, $roomItem['room']);
            $sheet->setCellValue('C' . $r, $roomItem['dormitory']);
            $sheet->setCellValue('D' . $r, $roomItem['total']);
            $sheet->setCellValue('E' . $r, $roomItem['active']);
            $sheet->setCellValue('F' . $r, $roomItem['cancelled']);
            $pct = $roomItem['total'] ? round(($roomItem['cancelled'] / $roomItem['total']) * 100) : 0;
            $sheet->setCellValue('G' . $r, $pct . '%');

            $sheet->getStyle('A' . $r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('B' . $r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('B' . $r)->getFont()->setBold(true);
            $sheet->getStyle('C' . $r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            $sheet->getStyle('D' . $r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('D' . $r)->getFont()->setBold(true);
            $sheet->getStyle('E' . $r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('E' . $r)->getFont()->getColor()->setRGB('15803D');
            $sheet->getStyle('F' . $r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('F' . $r)->getFont()->getColor()->setRGB('B91C1C');
            $sheet->getStyle('G' . $r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            if ($idx % 2 === 0) {
                $sheet->getStyle('A' . $r . ':G' . $r)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F8FAFC');
            }
        }
        if ($r >= $roomTableStart) {
            $sheet->getStyle('A' . ($roomTableStart - 1) . ':G' . $r)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('E2E8F0');
        }

        // ЗАГРУЗКА ОБОРУДОВАНИЯ
        $r += 2;
        $sheet->setCellValue('A' . $r, 'ЗАГРУЗКА ОБОРУДОВАНИЯ');
        $sheet->mergeCells('A' . $r . ':F' . $r);
        $sheet->getRowDimension($r)->setRowHeight(26);
        $sheet->getStyle('A' . $r)->getFont()->setBold(true)->setSize(12)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle('A' . $r)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('0E7490');
        $sheet->getStyle('A' . $r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT)->setVertical(Alignment::VERTICAL_CENTER);

        $r++;
        $eqHeaders = ['№', 'Оборудование', 'Тип', 'Общежитие', 'Бронирований', 'Доля от общего'];
        $eqColLetters = ['A', 'B', 'C', 'D', 'E', 'F'];
        $sheet->getRowDimension($r)->setRowHeight(24);
        foreach ($eqHeaders as $idx => $headerText) {
            $cell = $eqColLetters[$idx] . $r;
            $sheet->setCellValue($cell, $headerText);
            $sheet->getStyle($cell)->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
            $sheet->getStyle($cell)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('0891B2');
            $sheet->getStyle($cell)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
        }

        $eqTableStart = $r + 1;
        $mIdx = 1;
        foreach ($data['machineStats'] as $mItem) {
            $r++;
            $sheet->setCellValue('A' . $r, $mIdx++);
            $sheet->setCellValue('B' . $r, $mItem['name']);
            $sheet->setCellValue('C' . $r, $mItem['type']);
            $sheet->setCellValue('D' . $r, $mItem['dormitory']);
            $sheet->setCellValue('E' . $r, $mItem['total']);
            $share = $data['totalBookings'] ? round(($mItem['total'] / $data['totalBookings']) * 100, 1) : 0;
            $sheet->setCellValue('F' . $r, $share . '%');

            $sheet->getStyle('A' . $r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('B' . $r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            $sheet->getStyle('B' . $r)->getFont()->setBold(true);
            $sheet->getStyle('C' . $r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('D' . $r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            $sheet->getStyle('E' . $r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('E' . $r)->getFont()->setBold(true);
            $sheet->getStyle('F' . $r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            if ($mIdx % 2 === 0) {
                $sheet->getStyle('A' . $r . ':F' . $r)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F8FAFC');
            }
        }
        if ($r >= $eqTableStart) {
            $sheet->getStyle('A' . ($eqTableStart - 1) . ':F' . $r)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('E2E8F0');
        }

        foreach (range('A', 'G') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // ----------------- ЛИСТ 2: ВСЕ БРОНИРОВАНИЯ -----------------
        $sheet2 = $spreadsheet->createSheet();
        $sheet2->setTitle('Список бронирований');

        $sheet2->setCellValue('A1', "РЕЕСТР ВСЕХ БРОНИРОВАНИЙ ({$selectedDormName})");
        $sheet2->mergeCells('A1:H1');
        $sheet2->getRowDimension(1)->setRowHeight(32);
        $sheet2->getStyle('A1')->getFont()->setBold(true)->setSize(14)->getColor()->setRGB('FFFFFF');
        $sheet2->getStyle('A1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('334155');
        $sheet2->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);

        $sheet2->setCellValue('A2', "Период: {$from} — {$to}   |   Всего записей: " . count($data['bookings']));
        $sheet2->mergeCells('A2:H2');
        $sheet2->getRowDimension(2)->setRowHeight(20);
        $sheet2->getStyle('A2')->getFont()->setSize(10)->setItalic(true)->getColor()->setRGB('64748B');
        $sheet2->getStyle('A2')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F8FAFC');
        $sheet2->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);

        $regHeaders = ['ID', 'Общежитие', 'Комната', 'Житель', 'Оборудование', 'Начало', 'Конец', 'Статус'];
        $regCols = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'];
        $sheet2->getRowDimension(3)->setRowHeight(24);
        foreach ($regHeaders as $idx => $hText) {
            $c = $regCols[$idx] . '3';
            $sheet2->setCellValue($c, $hText);
            $sheet2->getStyle($c)->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
            $sheet2->getStyle($c)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('475569');
            $sheet2->getStyle($c)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
        }

        $row2 = 4;
        foreach ($data['bookings'] as $b) {
            $fio = trim($b['last_name'] . ' ' . $b['first_name'] . ' ' . ($b['patronymic'] ?? ''));
            $sheet2->setCellValue('A' . $row2, $b['id']);
            $sheet2->setCellValue('B' . $row2, $b['dormitory_name'] ?? ('Общежитие №' . ($b['dormitory_id'] ?? 1)));
            $sheet2->setCellValue('C' . $row2, $b['inidroom'] ?? '');
            $sheet2->setCellValue('D' . $row2, $fio);
            $sheet2->setCellValue('E' . $row2, ($b['type_machine'] ?? 'Машина') . ' #' . ($b['number_machine'] ?? ''));
            $sheet2->setCellValue('F' . $row2, $b['start_time']);
            $sheet2->setCellValue('G' . $row2, $b['end_time']);
            $sheet2->setCellValue('H' . $row2, $b['status']);

            $sheet2->getStyle('A' . $row2)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet2->getStyle('B' . $row2)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            $sheet2->getStyle('C' . $row2)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet2->getStyle('C' . $row2)->getFont()->setBold(true);
            $sheet2->getStyle('D' . $row2)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            $sheet2->getStyle('E' . $row2)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            $sheet2->getStyle('F' . $row2)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet2->getStyle('G' . $row2)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet2->getStyle('H' . $row2)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet2->getStyle('H' . $row2)->getFont()->setBold(true);

            $st = $b['status'] ?? '';
            if (in_array($st, ['Отменено', 'cancelled', 'Отмена'])) {
                $sheet2->getStyle('H' . $row2)->getFont()->getColor()->setRGB('991B1B');
                $sheet2->getStyle('H' . $row2)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FEE2E2');
            } elseif ($st === 'Подтверждено') {
                $sheet2->getStyle('H' . $row2)->getFont()->getColor()->setRGB('166534');
                $sheet2->getStyle('H' . $row2)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('DCFCE7');
            } else {
                $sheet2->getStyle('H' . $row2)->getFont()->getColor()->setRGB('1E40AF');
                $sheet2->getStyle('H' . $row2)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('DBEAFE');
            }

            if ($row2 % 2 === 1) {
                $sheet2->getStyle('A' . $row2 . ':G' . $row2)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F8FAFC');
            }
            $row2++;
        }

        if ($row2 > 4) {
            $sheet2->getStyle('A3:H' . ($row2 - 1))->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('E2E8F0');
            $sheet2->setAutoFilter('A3:H' . ($row2 - 1));
        }

        foreach (range('A', 'H') as $col) {
            $sheet2->getColumnDimension($col)->setAutoSize(true);
        }

        $spreadsheet->setActiveSheetIndex(0);

        $writer = new Xlsx($spreadsheet);
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="report_laundry_' . date('Y-m-d_H-i') . '.xlsx"');
        $writer->save('php://output');
        exit;
    }

    /**
     * Экспорт в Word
     */
    public function exportDocx()
    {
        if (!isset($_SESSION['admin_id'])) {
            $this->redirect('/login?error=' . urlencode('Доступ запрещён. Пожалуйста, войдите в систему.'));
        }

        if (($_SESSION['role'] ?? 0) !== 1) {
            $this->redirect('/booking?error=' . urlencode('Недостаточно прав для просмотра статистики.'));
        }

        $from = $_GET['from'] ?? date('Y-m-d', strtotime('-30 days'));
        $to   = $_GET['to']   ?? date('Y-m-d');
        $sessionDormId = !empty($_SESSION['dormitory_id']) ? (int)$_SESSION['dormitory_id'] : null;
        $dormitory_id = $sessionDormId !== null ? $sessionDormId : (!empty($_GET['dormitory_id']) ? (int)$_GET['dormitory_id'] : null);

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
            $from = date('Y-m-d', strtotime('-30 days'));
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
            $to = date('Y-m-d');
        }

        $dormitories = Dormitory::getAll($this->pdo);
        $selectedDormName = $this->resolveDormitoryName($dormitories, $dormitory_id);

        $data = $this->getReportData($from, $to, $dormitory_id);

        $phpWord = new PhpWord();
        $phpWord->setDefaultFontName('Arial');
        $phpWord->setDefaultFontSize(10);

        $section = $phpWord->addSection([
            'marginTop'    => 1000,
            'marginBottom' => 1000,
            'marginLeft'   => 1200,
            'marginRight'  => 1200
        ]);

        // Заголовок
        $section->addText("Отчёт по использованию прачечной", ['bold' => true, 'size' => 18, 'color' => '1E293B']);
        $section->addText("Период: {$from} — {$to}   |   Общежитие: {$selectedDormName}   |   Сформирован: " . date('d.m.Y H:i'), ['italic' => true, 'size' => 9, 'color' => '64748B']);
        $section->addTextBreak(1);

        // Ключевые показатели
        $section->addText("Ключевые показатели:", ['bold' => true, 'size' => 12, 'color' => '334155']);
        $kpiTable = $section->addTable(['borderSize' => 6, 'borderColor' => 'CCCCCC', 'cellMargin' => 80]);
        
        $kpis = [
            ['Всего бронирований', $data['totalBookings']],
            ['Активных (состоявшихся)', $data['activeCount']],
            ['Отменено', $data['cancelledCount'] . ' (' . $data['cancelledPercent'] . '%)'],
            ['Активных комнат', $data['uniqueRoomsCount']],
            ['Самая активная комната', $data['topRoomName'] . ' (' . $data['topRoomCount'] . ' стирок)'],
        ];
        foreach ($kpis as $kpi) {
            $kpiTable->addRow();
            $kpiTable->addCell(4500, ['bgColor' => 'F8FAFC'])->addText($kpi[0], ['bold' => true]);
            $kpiTable->addCell(4500)->addText((string)$kpi[1], ['bold' => true, 'color' => '0F172A']);
        }

        $section->addTextBreak(1);

        // 1. Статистика по комнатам
        $section->addText("1. Статистика по комнатам (активность проживающих):", ['bold' => true, 'size' => 12, 'color' => '334155']);
        $roomTable = $section->addTable(['borderSize' => 6, 'borderColor' => 'CCCCCC', 'cellMargin' => 80]);
        $roomTable->addRow();
        $roomTable->addCell(1800, ['bgColor' => '4F46E5'])->addText('Комната', ['bold' => true, 'color' => 'FFFFFF']);
        $roomTable->addCell(2600, ['bgColor' => '4F46E5'])->addText('Общежитие', ['bold' => true, 'color' => 'FFFFFF']);
        $roomTable->addCell(1600, ['bgColor' => '4F46E5'])->addText('Всего', ['bold' => true, 'color' => 'FFFFFF']);
        $roomTable->addCell(1500, ['bgColor' => '4F46E5'])->addText('Активных', ['bold' => true, 'color' => 'FFFFFF']);
        $roomTable->addCell(1500, ['bgColor' => '4F46E5'])->addText('Отменено', ['bold' => true, 'color' => 'FFFFFF']);

        $rCount = 0;
        foreach ($data['roomStats'] as $rItem) {
            $rCount++;
            if ($rCount > 40) break; // Топ 40 комнат
            $bgColor = ($rCount % 2 === 0) ? 'F8FAFC' : 'FFFFFF';
            $roomTable->addRow();
            $roomTable->addCell(1800, ['bgColor' => $bgColor])->addText($rItem['room'], ['bold' => true]);
            $roomTable->addCell(2600, ['bgColor' => $bgColor])->addText($rItem['dormitory']);
            $roomTable->addCell(1600, ['bgColor' => $bgColor])->addText((string)$rItem['total'], ['bold' => true]);
            $roomTable->addCell(1500, ['bgColor' => $bgColor])->addText((string)$rItem['active']);
            $roomTable->addCell(1500, ['bgColor' => $bgColor])->addText((string)$rItem['cancelled']);
        }

        $section->addTextBreak(1);

        // 2. Загрузка оборудования
        $section->addText("2. Загрузка оборудования:", ['bold' => true, 'size' => 12, 'color' => '334155']);
        $eqTable = $section->addTable(['borderSize' => 6, 'borderColor' => 'CCCCCC', 'cellMargin' => 80]);
        $eqTable->addRow();
        $eqTable->addCell(3000, ['bgColor' => '0891B2'])->addText('Оборудование', ['bold' => true, 'color' => 'FFFFFF']);
        $eqTable->addCell(2500, ['bgColor' => '0891B2'])->addText('Тип', ['bold' => true, 'color' => 'FFFFFF']);
        $eqTable->addCell(2000, ['bgColor' => '0891B2'])->addText('Общежитие', ['bold' => true, 'color' => 'FFFFFF']);
        $eqTable->addCell(1500, ['bgColor' => '0891B2'])->addText('Записей', ['bold' => true, 'color' => 'FFFFFF']);

        $mCount = 0;
        foreach ($data['machineStats'] as $mItem) {
            $mCount++;
            $bgColor = ($mCount % 2 === 0) ? 'F8FAFC' : 'FFFFFF';
            $eqTable->addRow();
            $eqTable->addCell(3000, ['bgColor' => $bgColor])->addText($mItem['name'], ['bold' => true]);
            $eqTable->addCell(2500, ['bgColor' => $bgColor])->addText($mItem['type']);
            $eqTable->addCell(2000, ['bgColor' => $bgColor])->addText($mItem['dormitory']);
            $eqTable->addCell(1500, ['bgColor' => $bgColor])->addText((string)$mItem['total'], ['bold' => true]);
        }

        $section->addTextBreak(1);

        // 3. Реестр бронирований
        $section->addText("3. Реестр бронирований (выборка):", ['bold' => true, 'size' => 12, 'color' => '334155']);
        $bookTable = $section->addTable(['borderSize' => 6, 'borderColor' => 'CCCCCC', 'cellMargin' => 60]);
        $bookTable->addRow();
        $bookTable->addCell(800, ['bgColor' => '475569'])->addText('ID', ['bold' => true, 'color' => 'FFFFFF', 'size' => 8]);
        $bookTable->addCell(2400, ['bgColor' => '475569'])->addText('Житель', ['bold' => true, 'color' => 'FFFFFF', 'size' => 8]);
        $bookTable->addCell(1000, ['bgColor' => '475569'])->addText('Комн.', ['bold' => true, 'color' => 'FFFFFF', 'size' => 8]);
        $bookTable->addCell(2000, ['bgColor' => '475569'])->addText('Машина', ['bold' => true, 'color' => 'FFFFFF', 'size' => 8]);
        $bookTable->addCell(1500, ['bgColor' => '475569'])->addText('Начало', ['bold' => true, 'color' => 'FFFFFF', 'size' => 8]);
        $bookTable->addCell(1300, ['bgColor' => '475569'])->addText('Статус', ['bold' => true, 'color' => 'FFFFFF', 'size' => 8]);

        $bLimit = 0;
        foreach ($data['bookings'] as $b) {
            $bLimit++;
            if ($bLimit > 100) break;
            $bgColor = ($bLimit % 2 === 0) ? 'F8FAFC' : 'FFFFFF';
            $fio = trim($b['last_name'] . ' ' . $b['first_name']);
            $bookTable->addRow();
            $bookTable->addCell(800, ['bgColor' => $bgColor])->addText((string)$b['id'], ['size' => 8]);
            $bookTable->addCell(2400, ['bgColor' => $bgColor])->addText($fio, ['size' => 8]);
            $bookTable->addCell(1000, ['bgColor' => $bgColor])->addText((string)($b['inidroom'] ?? ''), ['size' => 8, 'bold' => true]);
            $bookTable->addCell(2000, ['bgColor' => $bgColor])->addText(($b['type_machine'] ?? '') . ' #' . ($b['number_machine'] ?? ''), ['size' => 8]);
            $bookTable->addCell(1500, ['bgColor' => $bgColor])->addText(substr($b['start_time'], 5, 11), ['size' => 8]);
            $bookTable->addCell(1300, ['bgColor' => $bgColor])->addText($b['status'] ?? '', ['size' => 8]);
        }

        header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        header('Content-Disposition: attachment; filename="report_laundry_' . date('Y-m-d_H-i') . '.docx"');
        $writer = IOFactory::createWriter($phpWord, 'Word2007');
        $writer->save('php://output');
        exit;
    }

    /**
     * Определение названия общежития
     */
    private function resolveDormitoryName(array $dormitories, $dormitoryId): string
    {
        if (empty($dormitoryId)) {
            return 'Все общежития';
        }
        foreach ($dormitories as $d) {
            if ((int)$d->id === (int)$dormitoryId) {
                return $d->name ?: ('Общежитие №' . $d->number);
            }
        }
        return 'Общежитие №' . (int)$dormitoryId;
    }

    /**
     * Общая логика получения данных отчёта
     */
    private function getReportData($from, $to, $dormitoryId = null): array
    {
        $sessionDormId = !empty($_SESSION['dormitory_id']) ? (int)$_SESSION['dormitory_id'] : null;
        $effectiveDormId = $sessionDormId !== null ? $sessionDormId : (!empty($dormitoryId) ? (int)$dormitoryId : null);
        $bookingsData = Booking::getAll($this->pdo, $from, $to, '', $effectiveDormId);

        $totalBookings   = count($bookingsData);
        $cancelledCount  = 0;
        $activeCount     = 0;
        $dailyData       = [];
        $topMachines     = [];
        $machineStats    = [];
        $roomStats       = [];
        $dormitoryStats  = [];
        $typeStats       = ['washing' => 0, 'drying' => 0];
        $statusStats     = [
            'Подтверждено'            => 0,
            'Ожидание'                => 0,
            'Ожидание подтверждения'  => 0,
            'Отменено'                => 0
        ];
        $uniqueRooms = [];

        foreach ($bookingsData as $b) {
            $status = trim($b['status'] ?? '');
            if (in_array($status, ['cancelled', 'Отменено', 'Отмена'])) {
                $cancelledCount++;
                $statusStats['Отменено']++;
            } else {
                $activeCount++;
                if (isset($statusStats[$status])) {
                    $statusStats[$status]++;
                } else {
                    $statusStats['Ожидание']++;
                }
            }

            // По дням
            $day = substr($b['start_time'], 0, 10);
            $dailyData[$day] = ($dailyData[$day] ?? 0) + 1;

            // Тип оборудования
            $rawType = mb_strtolower($b['type_machine'] ?? '');
            if (str_contains($rawType, 'сушил')) {
                $typeStats['drying']++;
            } else {
                $typeStats['washing']++;
            }

            // По машинам
            $machineKey = ($b['type_machine'] ?? 'Машина') . ' #' . ($b['number_machine'] ?? $b['machine_id']);
            $dormName = $b['dormitory_name'] ?? ('Общежитие №' . ($b['dormitory_id'] ?? 1));
            
            if (!isset($machineStats[$machineKey])) {
                $machineStats[$machineKey] = [
                    'name'      => $machineKey,
                    'type'      => $b['type_machine'] ?? 'Стиральная машина',
                    'number'    => $b['number_machine'] ?? '',
                    'dormitory' => $dormName,
                    'total'     => 0,
                    'active'    => 0,
                    'cancelled' => 0
                ];
            }
            $machineStats[$machineKey]['total']++;
            if (in_array($status, ['cancelled', 'Отменено', 'Отмена'])) {
                $machineStats[$machineKey]['cancelled']++;
            } else {
                $machineStats[$machineKey]['active']++;
            }
            $topMachines[$machineKey] = ($topMachines[$machineKey] ?? 0) + 1;

            // По комнатам
            $roomNum = trim((string)($b['inidroom'] ?? ''));
            if ($roomNum === '') {
                $roomNum = 'Без номера';
            }
            
            $roomCompositeKey = $effectiveDormId ? $roomNum : ($dormName . ' — Комн. ' . $roomNum);
            $uniqueRooms[$effectiveDormId ? $roomNum : ($dormName . '_' . $roomNum)] = true;

            if (!isset($roomStats[$roomCompositeKey])) {
                $roomStats[$roomCompositeKey] = [
                    'room'         => $roomNum,
                    'dormitory'    => $dormName,
                    'dormitory_id' => $b['dormitory_id'] ?? 1,
                    'total'        => 0,
                    'active'       => 0,
                    'cancelled'    => 0
                ];
            }
            $roomStats[$roomCompositeKey]['total']++;
            if (in_array($status, ['cancelled', 'Отменено', 'Отмена'])) {
                $roomStats[$roomCompositeKey]['cancelled']++;
            } else {
                $roomStats[$roomCompositeKey]['active']++;
            }

            // По общежитиям
            $dId = !empty($b['dormitory_id']) ? (int)$b['dormitory_id'] : 1;
            $dName = !empty($b['dormitory_name']) ? $b['dormitory_name'] : ('Общежитие №' . $dId);
            if (!isset($dormitoryStats[$dId])) {
                $dormitoryStats[$dId] = [
                    'id'        => $dId,
                    'name'      => $dName,
                    'total'     => 0,
                    'active'    => 0,
                    'cancelled' => 0,
                    'rooms'     => []
                ];
            }
            $dormitoryStats[$dId]['total']++;
            if (in_array($status, ['cancelled', 'Отменено', 'Отмена'])) {
                $dormitoryStats[$dId]['cancelled']++;
            } else {
                $dormitoryStats[$dId]['active']++;
            }
            if (!empty($b['inidroom'])) {
                $dormitoryStats[$dId]['rooms'][$b['inidroom']] = true;
            }
        }

        // Сортировка общежитий по общему числу бронирований
        uasort($dormitoryStats, function($a, $b) {
            return $b['total'] <=> $a['total'];
        });

        // Сортировка машин
        arsort($topMachines);
        uasort($machineStats, function($a, $b) {
            return $b['total'] <=> $a['total'];
        });

        // Сортировка комнат по убыванию бронирований
        uasort($roomStats, function($a, $b) {
            if ($b['total'] === $a['total']) {
                return $b['active'] <=> $a['active'];
            }
            return $b['total'] <=> $a['total'];
        });

        // Самая активная комната
        $topRoomName = '—';
        $topRoomCount = 0;
        if (!empty($roomStats)) {
            $firstRoom = reset($roomStats);
            $topRoomName = 'Комн. ' . $firstRoom['room'];
            if (!$effectiveDormId && !empty($firstRoom['dormitory'])) {
                $topRoomName .= ' (' . $firstRoom['dormitory'] . ')';
            }
            $topRoomCount = $firstRoom['total'];
        }

        ksort($dailyData);

        return [
            'totalBookings'     => $totalBookings,
            'activeCount'       => $activeCount,
            'cancelledCount'    => $cancelledCount,
            'cancelledPercent'  => $totalBookings ? round($cancelledCount / $totalBookings * 100) : 0,
            'uniqueRoomsCount'  => count($uniqueRooms),
            'topRoomName'       => $topRoomName,
            'topRoomCount'      => $topRoomCount,
            'topMachines'       => array_slice($topMachines, 0, 5, true),
            'machineStats'      => $machineStats,
            'roomStats'         => $roomStats,
            'dormitoryStats'    => $dormitoryStats,
            'typeStats'         => $typeStats,
            'statusStats'       => $statusStats,
            'dailyLabels'       => array_keys($dailyData),
            'dailyCounts'       => array_values($dailyData),
            'bookings'          => $bookingsData,
            'from'              => $from,
            'to'                => $to,
            'dormitory_id'      => $effectiveDormId
        ];
    }
}