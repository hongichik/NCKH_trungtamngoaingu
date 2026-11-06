<?php

namespace App\Support\AiImport;

use ZipArchive;

/** Tạo file Word mẫu "cách soạn đề để AI đọc chính xác nhất" (docx tối giản, không cần thư viện). */
class TemplateDocx
{
    public const LINES = [
        ['h', 'MẪU SOẠN ĐỀ CHO AI DỰNG BÀI'],
        ['p', 'Mẹo để AI đọc đúng nhất: mỗi phần bắt đầu bằng tiêu đề "PHẦN 1", "PHẦN 2"…; ghi rõ dạng bài, file nghe, cách tính điểm; đáp án để ở cuối mỗi phần hoặc cuối đề.'],
        ['h', 'PHẦN 1 — Nghe điền từ (file nghe: track01.mp3) — mỗi câu 0,25 điểm'],
        ['p', 'Nghe và điền KHÔNG QUÁ HAI TỪ VÀ/HOẶC MỘT CON SỐ vào mỗi chỗ trống.'],
        ['p', '1. The library opens at ________.'],
        ['p', '2. Members can borrow up to ________ books.'],
        ['p', 'Đáp án: 1. 8.30 / half past eight   2. six / 6'],
        ['p', 'Lời thoại (nếu có, để AI tạo giọng đọc khi thiếu file): Welcome to the city library. We open at half past eight…'],
        ['h', 'PHẦN 2 — Đọc hiểu: True / False / Not Given — mỗi câu 0,5 điểm'],
        ['p', 'Đọc đoạn văn rồi chọn TRUE, FALSE hoặc NOT GIVEN.'],
        ['p', '(Dán đoạn văn ở đây)'],
        ['p', '3. ………   4. ………'],
        ['p', 'Đáp án: 3. TRUE   4. NOT GIVEN'],
        ['h', 'PHẦN 3 — Trắc nghiệm ngữ pháp'],
        ['p', '5. She ___ to school every day.  A. go   B. goes   C. going   D. gone'],
        ['p', 'Đáp án: 5. B'],
        ['h', 'PHẦN 4 — Viết đoạn văn (chấm theo thang IELTS)'],
        ['p', 'Write about your favourite place in your city (150–200 words).'],
        ['p', 'Ghi chú: phần nào cần nghe mà chưa có file, hệ thống sẽ hỏi bạn tải file lên hoặc tạo giọng đọc bằng AI từ lời thoại.'],
    ];

    public static function build(): string
    {
        $esc = fn ($s) => htmlspecialchars($s, ENT_XML1);
        $body = '';
        foreach (self::LINES as [$kind, $text]) {
            $rpr = $kind === 'h' ? '<w:rPr><w:b/><w:sz w:val="28"/></w:rPr>' : '';
            $body .= '<w:p><w:r>'.$rpr.'<w:t xml:space="preserve">'.$esc($text).'</w:t></w:r></w:p>';
        }
        $tmp = tempnam(sys_get_temp_dir(), 'docx');
        $zip = new ZipArchive;
        $zip->open($tmp, ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/></Relationships>');
        $zip->addFromString('word/document.xml', '<?xml version="1.0" encoding="UTF-8"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body>'.$body.'</w:body></w:document>');
        $zip->close();
        $bytes = file_get_contents($tmp);
        @unlink($tmp);

        return $bytes;
    }
}
