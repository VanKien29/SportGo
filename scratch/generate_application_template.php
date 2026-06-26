<?php
require __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\SimpleType\Jc;
use PhpOffice\PhpWord\SimpleType\JcTable;
use PhpOffice\PhpWord\Style\Language;

$phpWord = new PhpWord();
$phpWord->getSettings()->setThemeFontLang(new Language('vi-VN'));
$phpWord->setDefaultFontName('Times New Roman');
$phpWord->setDefaultFontSize(12);

$section = $phpWord->addSection();

// Header
$section->addText('CỘNG HÒA XÃ HỘI CHỦ NGHĨA VIỆT NAM', ['bold' => true, 'size' => 12], ['alignment' => Jc::CENTER]);
$section->addText('Độc lập - Tự do - Hạnh phúc', ['bold' => true, 'size' => 13], ['alignment' => Jc::CENTER]);
$section->addText('----------------', ['bold' => true], ['alignment' => Jc::CENTER]);
$section->addTextBreak(1);

// Title
$section->addText('ĐƠN ĐỀ NGHỊ ĐĂNG KÝ TRỞ THÀNH ĐỐI TÁC/CHỦ SÂN SPORTGO', ['bold' => true, 'size' => 14], ['alignment' => Jc::CENTER]);
$section->addTextBreak(1);

$section->addText('Kính gửi: Công ty/Đơn vị vận hành nền tảng SportGo', ['bold' => true, 'italic' => true], ['alignment' => Jc::CENTER]);
$section->addTextBreak(1);

$section->addText('Tôi/Chúng tôi làm đơn này đề nghị SportGo xem xét hồ sơ đăng ký trở thành đối tác/chủ sân trên nền tảng SportGo với các thông tin như sau:');
$section->addTextBreak(1);

// Part 1
$section->addText('1. Thông tin người đề nghị', ['bold' => true]);
$tableStyle = ['borderSize' => 6, 'borderColor' => '000000', 'cellMargin' => 80];
$phpWord->addTableStyle('table1', $tableStyle);
$table = $section->addTable('table1');
$table->addRow();
$table->addCell(3000)->addText('Họ và tên người đại diện:');
$table->addCell(6000)->addText('${applicant_full_name}');
$table->addRow();
$table->addCell(3000)->addText('Ngày sinh:');
$table->addCell(6000)->addText('${applicant_birth_date}');
$table->addRow();
$table->addCell(3000)->addText('Số CCCD/CMND:');
$table->addCell(6000)->addText('${id_number}');
$table->addRow();
$table->addCell(3000)->addText('Ngày cấp, nơi cấp:');
$table->addCell(6000)->addText('${id_issued_info}');
$table->addRow();
$table->addCell(3000)->addText('Số điện thoại liên hệ:');
$table->addCell(6000)->addText('${phone}');
$table->addRow();
$table->addCell(3000)->addText('Email liên hệ:');
$table->addCell(6000)->addText('${email}');
$table->addRow();
$table->addCell(3000)->addText('Địa chỉ thường trú:');
$table->addCell(6000)->addText('${applicant_address}');
$section->addTextBreak(1);

// Part 2
$section->addText('2. Thông tin cụm sân đăng ký', ['bold' => true]);
$table2 = $section->addTable('table1');
$table2->addRow();
$table2->addCell(3000)->addText('Tên cụm sân:');
$table2->addCell(6000)->addText('${venue_name}');
$table2->addRow();
$table2->addCell(3000)->addText('Địa chỉ cụm sân:');
$table2->addCell(6000)->addText('${venue_address}');
$table2->addRow();
$table2->addCell(3000)->addText('Số điện thoại sân:');
$table2->addCell(6000)->addText('${venue_phone}');
$table2->addRow();
$table2->addCell(3000)->addText('Tổng số sân con:');
$table2->addCell(6000)->addText('${court_count_total} sân');
$table2->addRow();
$table2->addCell(3000)->addText('Loại sân:');
$table2->addCell(6000)->addText('${court_types}');
$table2->addRow();
$table2->addCell(3000)->addText('Các tiện ích:');
$table2->addCell(6000)->addText('${amenities}');
$table2->addRow();
$table2->addCell(3000)->addText('Thời gian mở cửa:');
$table2->addCell(6000)->addText('${expected_opening_hours}');
$table2->addRow();
$table2->addCell(3000)->addText('Mức giá tham khảo:');
$table2->addCell(6000)->addText('${base_price_per_hour_label}');
$section->addTextBreak(1);

// Part 3
$section->addText('3. Thông tin thanh toán (Ngân hàng nhận doanh thu)', ['bold' => true]);
$table3 = $section->addTable('table1');
$table3->addRow();
$table3->addCell(3000)->addText('Tên ngân hàng:');
$table3->addCell(6000)->addText('${bank_name}');
$table3->addRow();
$table3->addCell(3000)->addText('Chi nhánh:');
$table3->addCell(6000)->addText('${bank_branch}');
$table3->addRow();
$table3->addCell(3000)->addText('Số tài khoản:');
$table3->addCell(6000)->addText('${account_number}');
$table3->addRow();
$table3->addCell(3000)->addText('Tên chủ tài khoản:');
$table3->addCell(6000)->addText('${account_holder_name}');
$section->addTextBreak(1);

// Part 4
$section->addText('Tôi/Chúng tôi xin cam đoan những thông tin khai báo trên là hoàn toàn đúng sự thật. Nếu có bất kỳ sai phạm nào, tôi/chúng tôi xin chịu hoàn toàn trách nhiệm trước pháp luật và các quy định của SportGo.');
$section->addTextBreak(2);

$table4 = $section->addTable();
$table4->addRow();
$table4->addCell(4500); // Empty left side
$cellRight = $table4->addCell(4500);
$cellRight->addText('Ký, ghi rõ họ tên', ['italic' => true], ['alignment' => Jc::CENTER]);
$cellRight->addTextBreak(4);
$cellRight->addText('${signature_owner}', ['bold' => true], ['alignment' => Jc::CENTER]);

$outputPath = __DIR__ . '/../storage/app/private/document-templates/partner_application_form_v1.docx';
$phpWord->save($outputPath, 'Word2007');
echo "Successfully generated template at $outputPath\n";
