<?php

namespace App\Services;

use App\Models\FiscalDocument;
use App\Models\Salon;
use DOMDocument;
use DOMElement;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class SzamlazzHuClient
{
    public const Endpoint = 'https://www.szamlazz.hu/szamla/';

    /** @return array{status: string, number: ?string, pdf: ?string, error: ?string} */
    public function issue(FiscalDocument $document): array
    {
        return $this->send($document->kind === 'invoice' ? 'action-xmlagentxmlfile' : 'action-szamla_agent_nyugta_create', $this->issueXml($document));
    }

    /** @return array{status: string, number: ?string, pdf: ?string, error: ?string} */
    public function query(FiscalDocument $document): array
    {
        $key = Salon::current()->billing_agent_key;
        if ($document->kind === 'invoice') {
            $data = ['szamlaagentkulcs' => $key, 'valaszVerzio' => '2', 'szamlaKulsoAzon' => $document->external_id];

            return $this->send('action-szamla_agent_pdf', $this->xml('xmlszamlapdf', $data));
        }
        $data = ['beallitasok' => ['szamlaagentkulcs' => $key, 'pdfLetoltes' => 'true'], 'fejlec' => ['rendelesSzam' => $document->external_id]];

        return $this->send('action-szamla_agent_nyugta_get', $this->xml('xmlnyugtaget', $data));
    }

    public function issueXml(FiscalDocument $document): string
    {
        $payload = $document->payload;
        $key = Salon::current()->billing_agent_key;
        $item = ['megnevezes' => $payload['service_name'], 'mennyiseg' => '1', 'mennyisegiEgyseg' => 'alkalom', 'nettoEgysegar' => $payload['net'], 'afakulcs' => $payload['vat']];
        if ($document->kind === 'receipt') {
            $item += ['netto' => $payload['net'], 'afa' => $payload['tax'], 'brutto' => (string) $payload['gross']];

            return $this->xml('xmlnyugtacreate', ['beallitasok' => ['szamlaagentkulcs' => $key, 'pdfLetoltes' => 'true'],
                'fejlec' => ['hivasAzonosito' => $document->external_id, 'elotag' => $payload['prefix'], 'fizmod' => $payload['payment_method'], 'penznem' => 'HUF', 'rendelesSzam' => $document->external_id], 'tetelek' => ['tetel' => $item]]);
        }
        $item += ['nettoErtek' => $payload['net'], 'afaErtek' => $payload['tax'], 'bruttoErtek' => (string) $payload['gross']];

        return $this->xml('xmlszamla', ['beallitasok' => ['szamlaagentkulcs' => $key, 'eszamla' => 'false', 'szamlaLetoltes' => 'true', 'valaszVerzio' => '2', 'szamlaKulsoAzon' => $document->external_id],
            'fejlec' => ['keltDatum' => $payload['issue_date'], 'teljesitesDatum' => $payload['performance_date'], 'fizetesiHataridoDatum' => $payload['issue_date'], 'fizmod' => $payload['payment_method'], 'penznem' => 'HUF', 'szamlaNyelve' => 'hu', 'rendelesSzam' => $document->external_id, 'elolegszamla' => 'false', 'vegszamla' => 'false', 'helyesbitoszamla' => 'false', 'dijbekero' => 'false'],
            'elado' => ['bank' => '', 'bankszamlaszam' => ''], 'vevo' => ['nev' => $payload['buyer_name'], 'irsz' => $payload['buyer_postcode'], 'telepules' => $payload['buyer_city'], 'cim' => $payload['buyer_address'], 'email' => $payload['buyer_email'] ?? '', 'sendEmail' => 'false', 'adoszam' => $payload['buyer_tax_number'] ?? ''], 'tetelek' => ['tetel' => $item]]);
    }

    /** @param array<string, mixed> $data */
    private function xml(string $rootName, array $data): string
    {
        $xml = new DOMDocument('1.0', 'UTF-8');
        $root = $xml->createElementNS('http://www.szamlazz.hu/'.$rootName, $rootName);
        $xml->appendChild($root);
        $this->append($xml, $root, $data);

        return $xml->saveXML();
    }

    /** @param array<string, mixed> $data */
    private function append(DOMDocument $xml, DOMElement $parent, array $data): void
    {
        foreach ($data as $name => $value) {
            $child = $xml->createElementNS($parent->namespaceURI, $name);
            $parent->appendChild($child);
            if (is_array($value)) {
                $this->append($xml, $child, $value);
            } else {
                $child->appendChild($xml->createTextNode((string) $value));
            }
        }
    }

    /** @return array{status: string, number: ?string, pdf: ?string, error: ?string} */
    private function send(string $field, string $xml): array
    {
        try {
            $response = Http::timeout(30)->connectTimeout(10)->withOptions(['allow_redirects' => false])->attach($field, $xml, 'document.xml', ['Content-Type' => 'application/xml'])->post(self::Endpoint);
        } catch (ConnectionException) {
            return ['status' => 'uncertain', 'number' => null, 'pdf' => null, 'error' => 'provider_connection_uncertain'];
        }
        $body = $response->body();
        if (! $response->successful() || strlen($body) > 15_000_000 || preg_match('/<!DOCTYPE|<!ENTITY/i', $body)) {
            return ['status' => 'uncertain', 'number' => null, 'pdf' => null, 'error' => 'provider_response_uncertain'];
        }
        $previous = libxml_use_internal_errors(true);
        try {
            $result = simplexml_load_string($body, \SimpleXMLElement::class, LIBXML_NONET);
            if ($result === false) {
                return ['status' => 'uncertain', 'number' => null, 'pdf' => null, 'error' => 'provider_invalid_xml'];
            }
            $success = (string) ($result->xpath('//*[local-name()="sikeres"]')[0] ?? '');
            if (! in_array($success, ['true', 'false'], true)) {
                return ['status' => 'uncertain', 'number' => null, 'pdf' => null, 'error' => 'provider_missing_result'];
            }
            if ($success !== 'true') {
                $code = (string) ($result->xpath('//*[local-name()="hibakod"]')[0] ?? 'unknown');

                return ['status' => in_array($code, ['71', '152', '338'], true) ? 'uncertain' : 'failed', 'number' => null, 'pdf' => null, 'error' => 'provider_'.preg_replace('/[^a-zA-Z0-9_-]/', '', substr($code, 0, 50))];
            }
            $number = (string) ($result->xpath('//*[local-name()="szamlaszam" or local-name()="nyugtaszam"]')[0] ?? '');
            $encoded = (string) ($result->xpath('//*[local-name()="pdf" or local-name()="nyugtaPdf"]')[0] ?? '');
            $pdf = base64_decode(preg_replace('/\s+/', '', $encoded), true);
            if ($number === '') {
                return ['status' => 'uncertain', 'number' => null, 'pdf' => null, 'error' => 'provider_missing_number'];
            }

            return ['status' => 'issued', 'number' => mb_substr($number, 0, 255), 'pdf' => is_string($pdf) && str_starts_with($pdf, '%PDF-') ? base64_encode($pdf) : null, 'error' => null];
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }
}
