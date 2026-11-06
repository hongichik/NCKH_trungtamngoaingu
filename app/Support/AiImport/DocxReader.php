<?php

namespace App\Support\AiImport;

use ZipArchive;

/**
 * Đọc file Word (.docx) theo đúng thứ tự: đoạn văn, bảng (mỗi dòng "ô | ô | ô") và HÌNH ẢNH nhúng.
 * Mỗi hình được đánh số theo thứ tự xuất hiện và để lại dấu "[Hình N]" đúng vị trí trong chữ,
 * để biết hình nào thuộc bài tập nào. Không cần thư viện ngoài (docx là file zip).
 */
class DocxReader
{
    /** Định dạng ảnh gửi được cho AI / hiển thị trên web (emf, wmf… bỏ qua). */
    public const IMAGE_EXT = ['png', 'jpg', 'jpeg', 'gif', 'webp', 'bmp'];

    public function text(string $path): string
    {
        return $this->read($path, false)['text'];
    }

    /**
     * @return array{text: string, images: list<array{n: int, ext: string, bytes: string}>}
     */
    public function read(string $path, bool $withImages = true): array
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw new \InvalidArgumentException('Không mở được file Word.');
        }
        $xml = $zip->getFromName('word/document.xml');
        if ($xml === false) {
            $zip->close();
            throw new \InvalidArgumentException('File Word không có nội dung (word/document.xml).');
        }
        // rId → đường dẫn ảnh trong gói (word/media/image1.png)
        $rels = [];
        if (($relXml = $zip->getFromName('word/_rels/document.xml.rels')) !== false) {
            $rd = new \DOMDocument;
            $rd->loadXML($relXml, LIBXML_NONET);
            foreach ($rd->getElementsByTagName('Relationship') as $r) {
                if (str_ends_with((string) $r->getAttribute('Type'), '/image')) {
                    $rels[$r->getAttribute('Id')] = 'word/'.ltrim(str_replace('../', '', $r->getAttribute('Target')), '/');
                }
            }
        }

        $dom = new \DOMDocument;
        $dom->loadXML($xml, LIBXML_NONET);
        $xp = new \DOMXPath($dom);
        $xp->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
        $xp->registerNamespace('a', 'http://schemas.openxmlformats.org/drawingml/2006/main');
        $xp->registerNamespace('r', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');
        $xp->registerNamespace('v', 'urn:schemas-microsoft-com:vml');

        $images = [];
        $seen = [];   // cùng một ảnh dùng lại nhiều lần → cùng số
        $imageMarker = function (\DOMNode $n) use ($xp, $rels, $zip, $withImages, &$images, &$seen) {
            $blip = $xp->query('.//a:blip/@r:embed | .//v:imagedata/@r:id', $n)->item(0);
            $rid = $blip?->nodeValue;
            $target = $rid ? ($rels[$rid] ?? null) : null;
            $ext = $target ? strtolower(pathinfo($target, PATHINFO_EXTENSION)) : '';
            if (! $target || ! in_array($ext, self::IMAGE_EXT, true)) {
                return ' [Hình] ';
            }
            if (! isset($seen[$target])) {
                $seen[$target] = count($seen) + 1;
                if ($withImages && ($bytes = $zip->getFromName($target)) !== false) {
                    $images[] = ['n' => $seen[$target], 'ext' => $ext, 'bytes' => $bytes];
                }
            }

            return ' [Hình '.$seen[$target].'] ';
        };

        $para = function (\DOMNode $p) use ($xp, $imageMarker) {
            $out = '';
            foreach ($xp->query('.//w:t|.//w:tab|.//w:br|.//w:drawing|.//w:pict', $p) as $n) {
                $out .= match ($n->localName) {
                    't' => $n->textContent,
                    'tab' => "\t",
                    'br' => "\n",
                    default => $imageMarker($n),
                };
            }
            // Danh sách đánh số của Word không nằm trong chữ → thêm gạch đầu dòng.
            if ($xp->query('./w:pPr/w:numPr', $p)->length && trim($out) !== '') {
                $out = '• '.$out;
            }

            return rtrim($out);
        };

        $lines = [];
        foreach ($xp->query('//w:body')->item(0)?->childNodes ?? [] as $node) {
            if ($node->localName === 'p') {
                $lines[] = $para($node);
            } elseif ($node->localName === 'tbl') {
                foreach ($xp->query('./w:tr', $node) as $tr) {
                    $cells = [];
                    foreach ($xp->query('./w:tc', $tr) as $tc) {
                        $cells[] = trim(implode(' ', array_map($para, iterator_to_array($xp->query('.//w:p', $tc)))));
                    }
                    $lines[] = implode(' | ', $cells);
                }
                $lines[] = '';
            }
        }
        $zip->close();

        return ['text' => trim(preg_replace("/\n{3,}/", "\n\n", implode("\n", $lines))), 'images' => $images];
    }

    /** Chuẩn hoá ảnh (GD): đổi sang JPEG nền trắng, cạnh dài tối đa $max px. Trả về bytes JPEG hoặc null nếu không đọc được. */
    public static function normalizeImage(string $bytes, int $max = 1600): ?string
    {
        $img = @imagecreatefromstring($bytes);
        if (! $img) {
            return null;
        }
        [$w, $h] = [imagesx($img), imagesy($img)];
        $scale = min(1, $max / max($w, $h));
        $nw = max(1, (int) round($w * $scale));
        $nh = max(1, (int) round($h * $scale));
        $out = imagecreatetruecolor($nw, $nh);
        imagefill($out, 0, 0, imagecolorallocate($out, 255, 255, 255));
        imagecopyresampled($out, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
        ob_start();
        imagejpeg($out, null, 85);

        return (string) ob_get_clean();
    }
}
