<?php

namespace App\Services;

use App\Models\Company;
use App\Models\CompanySettings;
use App\Models\Offer;
use App\Models\ProjectWorkOrder;
use App\Models\User;
use Mpdf\Mpdf;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Settings;
use PhpOffice\PhpWord\Shared\Html;

class ProjectWorkOrderDocument
{
    public function response(ProjectWorkOrder $order, string $format, bool $download)
    {
        $binary = $format === 'docx' ? $this->word($order) : $this->pdf($order);
        $type = $format === 'docx' ? 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' : 'application/pdf';

        return response($binary, 200, [
            'Content-Type' => $type, 'Cache-Control' => 'private, no-store',
            'Content-Disposition' => ($download || $format === 'docx' ? 'attachment' : 'inline').'; filename="'.$order->filename($format).'"',
        ]);
    }

    public function html(ProjectWorkOrder $order): string
    {
        $offer = new Offer;
        $offer->forceFill([
            'offer_number' => $order->number, 'offer_full_number' => $order->number, 'offer_title' => $order->title,
            'created_at' => $order->issued_on ?? today(), 'design_style' => $order->issuer_snapshot['offer_design_style'] ?? null,
            'text_sections' => $order->text_sections, 'price_sections' => $order->price_sections,
            'show_total' => true, 'show_unit_prices' => true, 'kwota_netto' => $order->net_cents / 100,
            'delegations' => [], 'notes' => null,
        ]);
        $companySettings = (new CompanySettings)->forceFill($order->issuer_snapshot);
        $offer->setRelation('company', (new Company)->forceFill($order->supplier_snapshot ?? []));
        $offer->setRelation('assignedUser', (new User)->forceFill(['name' => $order->our_contact]));
        $offer->setRelation('offerDelegation', null);

        return view('offers.pdf', [
            'offer' => $offer, 'companySettings' => $companySettings,
            'logoBase64' => $order->issuer_snapshot['logo'] ?? null,
            'signature' => null, 'sectionsOnly' => false, 'workOrder' => $order,
        ])->render();
    }

    public function pdf(ProjectWorkOrder $order): string
    {
        $mpdf = new Mpdf(['mode' => 'utf-8', 'format' => 'A4', 'margin_top' => 12, 'margin_bottom' => 20, 'margin_left' => 15, 'margin_right' => 15]);
        $mpdf->SetHTMLFooter('<div style="font-size:8pt;color:#777;text-align:right">'.e($order->number).' · Strona {PAGENO} z {nbpg}</div>');
        $mpdf->WriteHTML($this->html($order));

        return $mpdf->Output('', 'S');
    }

    public function word(ProjectWorkOrder $order): string
    {
        Settings::setOutputEscapingEnabled(true);
        $word = new PhpWord;
        $word->setDefaultFontName('Calibri');
        $word->setDefaultFontSize(11);
        $section = $word->addSection(['pageSizeW' => 11906, 'pageSizeH' => 16838, 'marginLeft' => 1134, 'marginRight' => 1134, 'marginTop' => 850, 'marginBottom' => 850]);
        $header = $section->addHeader();
        $logo = $order->issuer_snapshot['logo'] ?? '';
        if (preg_match('/^data:image\/(png|jpeg|webp);base64,(.+)$/s', $logo, $matches)) {
            $bytes = base64_decode($matches[2], true);
            if ($bytes !== false && @getimagesizefromstring($bytes)) {
                $header->addImage($bytes, ['height' => 38]);
            }
        }
        $header->addText($order->number, ['bold' => true], ['alignment' => 'right']);
        $header->addText('Data zlecenia: '.($order->issued_on?->format('d.m.Y') ?? '........................'), [], ['alignment' => 'right']);
        $section->addText($order->title, ['bold' => true, 'size' => 18]);
        if ($order->status === 'draft' || $order->is_template) {
            $section->addText($order->is_template ? 'WZÓR ZLECENIA' : 'ROBOCZE – niewystawione', ['bold' => true]);
        }
        $parties = $section->addTable(['borderSize' => 0, 'cellMargin' => 100]);
        $parties->addRow();
        foreach (['ZAMAWIAJĄCY' => $order->issuer_snapshot, 'WYKONAWCA' => $order->supplier_snapshot ?? []] as $label => $party) {
            $cell = $parties->addCell(4800);
            $cell->addText($label, ['bold' => true]);
            foreach (['name', 'address', 'postcode', 'city', 'nip', 'email', 'phone'] as $key) {
                if (! empty($party[$key])) {
                    $cell->addText(($key === 'nip' ? 'NIP: ' : '').$party[$key]);
                }
            }
        }
        $section->addText('Miejsce realizacji / wystawienia: '.($order->place ?: '........................'));
        $section->addText('Oferta wykonawcy: '.($order->offer_reference ?: '—').($order->offer_date ? ' / '.$order->offer_date->format('d.m.Y') : ''));
        $section->addText('Kontakt zamawiającego: '.($order->our_contact ?: '........................'));
        $section->addText('Kontakt wykonawcy: '.($order->supplier_contact ?: '........................'));
        $content = app(OfferContentService::class);
        $writeSections = function (string $placement) use ($section, $order, $content) {
            foreach ($order->text_sections as $text) {
                if ($text['placement'] !== $placement || trim(strip_tags($text['content'])) === '') {
                    continue;
                }
                $section->addText($text['name'], ['bold' => true], ['keepNext' => true]);
                Html::addHtml($section, $content->wordHtml($text['content']), false, false);
            }
        };
        $writeSections('before_price');
        foreach ($order->price_sections as $price) {
            $section->addText($price['name'], ['bold' => true], ['keepNext' => true]);
            $table = $section->addTable(['borderSize' => 4, 'borderColor' => 'DDDDDD', 'cellMargin' => 80]);
            $table->addRow(null, ['tblHeader' => true]);
            foreach (['Opis', 'Ilość', 'J.m.', 'Cena netto', 'Wartość netto'] as $label) {
                $table->addCell()->addText($label, ['bold' => true]);
            }
            foreach ($price['rows'] as $row) {
                $table->addRow(null, ['cantSplit' => true]);
                foreach ([$row['opis'], $row['ilosc'], $row['jedn'], $this->money((int) round($row['cena_jedn'] * 100)), $this->money($row['net_cents'])] as $value) {
                    $table->addCell()->addText((string) $value);
                }
            }
        }
        $section->addText('Netto: '.$this->money($order->net_cents).' | VAT '.$order->vat_rate.'%: '.$this->money($order->vat_cents).' | Brutto: '.$this->money($order->net_cents + $order->vat_cents), ['bold' => true]);
        $section->addText('Zaliczka: '.$this->money($order->advance_cents).' | Pozostało: '.$this->money($order->net_cents + $order->vat_cents - $order->advance_cents));
        $writeSections('after_price');
        if ($order->attachment_snapshot) {
            $section->addText('Załączniki', ['bold' => true]);
            foreach ($order->attachment_snapshot as $attachment) {
                $section->addText($attachment['name']);
            }
        }
        $signatures = $section->addTable();
        $signatures->addRow();
        foreach (['ZAMAWIAJĄCY' => $order->our_contact, 'WYKONAWCA – potwierdzenie przyjęcia' => $order->supplier_contact] as $label => $name) {
            $cell = $signatures->addCell(4800);
            $cell->addTextBreak(2);
            $cell->addText('................................................');
            $cell->addText($name ?: '................................................');
            $cell->addText($label);
            $cell->addText('Data: ........................');
        }
        $section->addFooter()->addPreserveText($order->number.' · Strona {PAGE} z {NUMPAGES}');
        $path = tempnam(sys_get_temp_dir(), 'work-order-');
        try {
            IOFactory::createWriter($word, 'Word2007')->save($path);

            return file_get_contents($path);
        } finally {
            @unlink($path);
        }
    }

    private function money(int $cents): string
    {
        return number_format($cents / 100, 2, ',', ' ').' zł';
    }
}
