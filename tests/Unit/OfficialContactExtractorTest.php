<?php

namespace Tests\Unit;

use App\Support\Intelligence\OfficialContactExtractor;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OfficialContactExtractorTest extends TestCase
{
    #[Test]
    public function it_extracts_only_business_contacts_and_official_links(): void
    {
        $extractor = new OfficialContactExtractor;

        $result = $extractor->extract(<<<'HTML'
            <html>
                <body>
                    <a href="mailto:info@example.com">Info</a>
                    <a href="mailto:founder@example.com">Founder</a>
                    <a href="https://wa.me/966500000000">WhatsApp</a>
                    <a href="/contact">Contact</a>
                    <a href="https://instagram.com/exampleco">Instagram</a>
                    <form action="/contact">
                        <input type="text" name="name">
                    </form>
                    <p>Call us on +966 50 000 0000</p>
                </body>
            </html>
        HTML, 'https://example.com');

        $this->assertSame(
            ['info@example.com'],
            array_values(array_map(
                fn (array $contact): string => $contact['contact_value'],
                array_values(array_filter(
                    $result['contacts'],
                    fn (array $contact): bool => $contact['contact_type'] === 'official_email',
                )),
            )),
        );

        $this->assertFalse(collect($result['contacts'])->contains(
            fn (array $contact): bool => $contact['contact_value'] === 'founder@example.com',
        ));
        $this->assertTrue(collect($result['contacts'])->contains(
            fn (array $contact): bool => $contact['contact_type'] === 'official_phone',
        ));
        $this->assertTrue(collect($result['contacts'])->contains(
            fn (array $contact): bool => $contact['contact_type'] === 'whatsapp',
        ));
        $this->assertTrue(collect($result['contacts'])->contains(
            fn (array $contact): bool => $contact['contact_type'] === 'contact_form',
        ));
        $this->assertSame(['https://instagram.com/exampleco'], $result['social_links']);
        $this->assertSame(['https://example.com/contact'], $result['contact_pages']);
    }
}
