<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;


final class ContractCoverageTest extends TestCase
{
    use RefreshDatabase;

    private const NOVELA = '11111111-1111-4111-8111-111111111111';
    private const CIENCIA = '22222222-2222-4222-8222-222222222222';
    private const MISSING = '00000000-0000-4000-8000-000000000000';
   
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('library:ensure-admin')->assertExitCode(0);
        Storage::fake('local');
    }

    private function login(string $user = 'admin', string $pass = 'Admin#12345'): string
    {
        return (string) $this->postJson('/api/v1/auth/login', ['username' => $user, 'password' => $pass])
            ->assertOk()->json('access_token');
    }

    private function h(string $token): array
    {
        return ['Authorization' => 'Bearer ' . $token];
    }

    private function librarian(string $adminToken): string
    {
        $this->postJson('/api/v1/users', ['username' => 'ana', 'password' => 'Secreto#123', 'role' => 'librarian'], $this->h($adminToken))
            ->assertCreated();

        return $this->login('ana', 'Secreto#123');
    }

    private function book(string $token, array $override = []): array
    {
        return $this->postJson('/api/v1/books', array_merge([
            'title' => 'Cien años de soledad',
            'author' => 'Gabriel García Márquez',
            'isbn' => '9780307474728',
            'category_id' => self::NOVELA,
            'total_copies' => 3,
        ], $override), $this->h($token))->assertCreated()->json();
    }

    private function member(string $token, string $doc): string
    {
        return (string) $this->postJson('/api/v1/members', ['full_name' => 'Socio ' . $doc, 'document_number' => $doc], $this->h($token))
            ->assertCreated()->json('id');
    }

    private function assertErrorBody($response, int $status, string $code): void
    {
        $response->assertStatus($status)
            ->assertJsonStructure(['code', 'message', 'details'])
            ->assertJsonPath('code', $code);
        $this->assertIsArray($response->json('details'));
        $this->assertStringNotContainsString('password_hash', (string) $response->getContent());
    }



    public function test_every_protected_route_rejects_a_missing_or_tampered_token_with_401(): void
    {
        $routes = [
            ['GET', '/api/v1/categories'], ['GET', '/api/v1/books'], ['GET', '/api/v1/books/' . self::MISSING],
            ['GET', '/api/v1/books/' . self::MISSING . '/cover'], ['POST', '/api/v1/books'],
            ['PUT', '/api/v1/books/' . self::MISSING], ['DELETE', '/api/v1/books/' . self::MISSING],
            ['DELETE', '/api/v1/books/' . self::MISSING . '/cover'], ['POST', '/api/v1/users'],
            ['GET', '/api/v1/members'], ['GET', '/api/v1/members/' . self::MISSING], ['POST', '/api/v1/members'],
            ['GET', '/api/v1/loans'], ['GET', '/api/v1/loans/overdue'], ['GET', '/api/v1/loans/' . self::MISSING],
            ['POST', '/api/v1/loans'], ['POST', '/api/v1/loans/' . self::MISSING . '/items/' . self::MISSING . '/return'],
            ['GET', '/api/v1/reports/most-borrowed'],
        ];

        foreach ($routes as [$method, $uri]) {
            $this->assertErrorBody($this->json($method, $uri), 401, 'UNAUTHENTICATED');
            $this->assertErrorBody($this->json($method, $uri, [], $this->h('aaa.bbb.ccc')), 401, 'UNAUTHENTICATED');
        }
    }

    public function test_librarian_is_forbidden_on_admin_only_routes(): void
    {
        $admin = $this->login();
        $librarian = $this->librarian($admin);
        $book = $this->book($admin);

        $this->assertErrorBody($this->postJson('/api/v1/users', ['username' => 'x', 'password' => 'Secreto#123', 'role' => 'librarian'], $this->h($librarian)), 403, 'FORBIDDEN_ROLE');
        $this->assertErrorBody($this->putJson('/api/v1/books/' . $book['id'], [], $this->h($librarian)), 403, 'FORBIDDEN_ROLE');
        $this->assertErrorBody($this->deleteJson('/api/v1/books/' . $book['id'], [], $this->h($librarian)), 403, 'FORBIDDEN_ROLE');
        $this->assertErrorBody($this->deleteJson('/api/v1/books/' . $book['id'] . '/cover', [], $this->h($librarian)), 403, 'FORBIDDEN_ROLE');
        $this->assertErrorBody($this->getJson('/api/v1/reports/most-borrowed?from=2026-01-01&to=2026-12-31', $this->h($librarian)), 403, 'FORBIDDEN_ROLE');
    }

    public function test_unknown_resources_return_404_with_the_single_error_body(): void
    {
        $admin = $this->login();
        $payload = ['title' => 'T', 'author' => 'A', 'category_id' => self::NOVELA, 'total_copies' => 1];

        $this->assertErrorBody($this->getJson('/api/v1/books/' . self::MISSING, $this->h($admin)), 404, 'NOT_FOUND');
        $this->assertErrorBody($this->getJson('/api/v1/books/not-a-uuid', $this->h($admin)), 404, 'NOT_FOUND');
        $this->assertErrorBody($this->putJson('/api/v1/books/' . self::MISSING, $payload, $this->h($admin)), 404, 'NOT_FOUND');
        $this->assertErrorBody($this->deleteJson('/api/v1/books/' . self::MISSING, [], $this->h($admin)), 404, 'NOT_FOUND');
        $this->assertErrorBody($this->getJson('/api/v1/members/' . self::MISSING, $this->h($admin)), 404, 'NOT_FOUND');
        $this->assertErrorBody($this->getJson('/api/v1/loans/' . self::MISSING, $this->h($admin)), 404, 'NOT_FOUND');
        $this->assertErrorBody(
            $this->postJson('/api/v1/loans/' . self::MISSING . '/items/' . self::MISSING . '/return', [], $this->h($admin)),
            404,
            'NOT_FOUND',
        );
    }

    public function test_categories_are_the_five_seeded_ones_ordered_by_name(): void
    {
        $admin = $this->login();

        $response = $this->getJson('/api/v1/categories', $this->h($admin))->assertOk();
        $names = array_column($response->json('items'), 'name');

        $this->assertSame(['Ciencia', 'Historia', 'Infantil', 'Novela', 'Tecnología'], $names);
    }

 

    public function test_books_can_be_filtered_by_category(): void
    {
        $admin = $this->login();
        $this->book($admin);
        $this->book($admin, ['title' => 'Cosmos', 'isbn' => '9780000000011', 'category_id' => self::CIENCIA]);

        $this->getJson('/api/v1/books?category_id=' . self::CIENCIA, $this->h($admin))
            ->assertOk()->assertJsonPath('total', 1)->assertJsonPath('items.0.title', 'Cosmos')
            ->assertJsonPath('items.0.category.name', 'Ciencia');
        $this->getJson('/api/v1/books', $this->h($admin))
            ->assertOk()->assertJsonPath('total', 2)->assertJsonPath('items.0.title', 'Cien años de soledad');
    }

    public function test_book_validation_errors_use_the_single_body(): void
    {
        $admin = $this->login();

        $this->assertErrorBody($this->postJson('/api/v1/books', [], $this->h($admin)), 422, 'VALIDATION_ERROR');
        $this->assertErrorBody(
            $this->postJson('/api/v1/books', ['title' => 'T', 'author' => 'A', 'isbn' => '123', 'category_id' => self::NOVELA, 'total_copies' => 1], $this->h($admin)),
            422,
            'VALIDATION_ERROR',
        );
        $this->assertErrorBody(
            $this->postJson('/api/v1/books', ['title' => 'T', 'author' => 'A', 'isbn' => '9780000000011', 'category_id' => self::MISSING, 'total_copies' => 1], $this->h($admin)),
            422,
            'VALIDATION_ERROR',
        );
    }


    public function test_cover_can_be_uploaded_read_and_removed(): void
    {
        $admin = $this->login();
        $book = $this->book($admin);
        $this->assertNull($book['cover_key']);
        $headers = ['HTTP_AUTHORIZATION' => 'Bearer ' . $admin, 'HTTP_ACCEPT' => 'application/json'];
        $file = UploadedFile::fake()->createWithContent('cover.png', (string) base64_decode(self::PNG));

        $uploaded = $this->call('POST', '/api/v1/books/' . $book['id'] . '/cover', [], [], ['file' => $file], $headers)->assertOk();
        $key = $uploaded->json('cover_key');
        $this->assertNotNull($key);
        $this->assertNotSame('', $key);
        $this->assertStringNotContainsString('/', (string) $key, 'la clave es opaca, nunca una ruta');

        $this->get('/api/v1/books/' . $book['id'] . '/cover', $this->h($admin))
            ->assertOk()->assertHeader('Content-Type', 'image/png');

        $this->deleteJson('/api/v1/books/' . $book['id'] . '/cover', [], $this->h($admin))->assertNoContent();
        $this->getJson('/api/v1/books/' . $book['id'], $this->h($admin))->assertJsonPath('cover_key', null);
        $this->assertErrorBody($this->getJson('/api/v1/books/' . $book['id'] . '/cover', $this->h($admin)), 404, 'NOT_FOUND');
    }

    public function test_cover_must_be_jpg_or_png_by_content(): void
    {
        $admin = $this->login();
        $book = $this->book($admin);
        $headers = ['HTTP_AUTHORIZATION' => 'Bearer ' . $admin, 'HTTP_ACCEPT' => 'application/json'];
        $fake = UploadedFile::fake()->createWithContent('cover.png', 'esto no es una imagen');

        $response = $this->call('POST', '/api/v1/books/' . $book['id'] . '/cover', [], [], ['file' => $fake], $headers);

        $this->assertErrorBody($response, 422, 'VALIDATION_ERROR');
    }


    public function test_members_can_be_created_listed_searched_and_read_by_a_librarian(): void
    {
        $librarian = $this->librarian($this->login());
        $id = $this->member($librarian, '1001');
        $this->member($librarian, '2002');

        $this->assertErrorBody(
            $this->postJson('/api/v1/members', ['full_name' => 'Otro', 'document_number' => '1001'], $this->h($librarian)),
            409,
            'DOCUMENT_TAKEN',
        );
        $this->assertErrorBody($this->postJson('/api/v1/members', ['full_name' => ''], $this->h($librarian)), 422, 'VALIDATION_ERROR');

        $this->getJson('/api/v1/members', $this->h($librarian))->assertOk()->assertJsonPath('total', 2)->assertJsonPath('page', 1);
        $this->getJson('/api/v1/members?q=2002', $this->h($librarian))->assertOk()->assertJsonPath('total', 1);
        $this->getJson('/api/v1/members?q=socio', $this->h($librarian))->assertOk()->assertJsonPath('total', 2);
        $this->getJson('/api/v1/members/' . $id, $this->h($librarian))
            ->assertOk()->assertJsonPath('document_number', '1001')->assertJsonPath('active_loans', 0);
    }



    public function test_loans_can_be_listed_by_range_status_and_member(): void
    {
        $admin = $this->login();
        $book = $this->book($admin);
        $m1 = $this->member($admin, '1001');
        $m2 = $this->member($admin, '2002');
        $loan = $this->postJson('/api/v1/loans', ['member_id' => $m1, 'items' => [['book_id' => $book['id']]]], $this->h($admin))
            ->assertCreated()->json();
        $today = gmdate('Y-m-d');

        $this->getJson("/api/v1/loans?from={$today}&to={$today}", $this->h($admin))
            ->assertOk()->assertJsonPath('total', 1)->assertJsonPath('items.0.id', $loan['id']);
        $this->getJson('/api/v1/loans?status=open&member_id=' . $m1, $this->h($admin))->assertOk()->assertJsonPath('total', 1);
        $this->getJson('/api/v1/loans?status=closed', $this->h($admin))->assertOk()->assertJsonPath('total', 0);
        $this->getJson('/api/v1/loans?status=overdue', $this->h($admin))->assertOk()->assertJsonPath('total', 0);
        $this->getJson('/api/v1/loans?member_id=' . $m2, $this->h($admin))->assertOk()->assertJsonPath('total', 0);
        $this->getJson('/api/v1/loans?from=2000-01-01&to=2000-01-31', $this->h($admin))->assertOk()->assertJsonPath('items', []);

        $this->assertErrorBody($this->getJson('/api/v1/loans?from=2026-10-31&to=2026-10-01', $this->h($admin)), 422, 'INVALID_DATE_RANGE');
        $this->assertErrorBody($this->getJson('/api/v1/loans?status=lost', $this->h($admin)), 422, 'VALIDATION_ERROR');
    }

    public function test_loan_with_unknown_member_or_book_is_404_and_saves_nothing(): void
    {
        $admin = $this->login();
        $book = $this->book($admin);
        $member = $this->member($admin, '1001');

        $this->assertErrorBody(
            $this->postJson('/api/v1/loans', ['member_id' => self::MISSING, 'items' => [['book_id' => $book['id']]]], $this->h($admin)),
            404,
            'NOT_FOUND',
        );
        $this->assertErrorBody(
            $this->postJson('/api/v1/loans', ['member_id' => $member, 'items' => [['book_id' => self::MISSING]]], $this->h($admin)),
            404,
            'NOT_FOUND',
        );
        $this->getJson('/api/v1/books/' . $book['id'], $this->h($admin))->assertJsonPath('available_copies', 3);
    }

    public function test_loan_with_more_than_three_lines_is_422(): void
    {
        $admin = $this->login();
        $member = $this->member($admin, '1001');
        $items = [];
        foreach (['9780000000011', '9780000000028', '9780000000035', '9780000000042'] as $n => $isbn) {
            $items[] = ['book_id' => $this->book($admin, ['title' => 'Libro ' . $n, 'isbn' => $isbn])['id']];
        }

        $this->assertErrorBody($this->postJson('/api/v1/loans', ['member_id' => $member, 'items' => $items], $this->h($admin)), 422, 'VALIDATION_ERROR');
    }

    public function test_loan_response_has_the_contract_shape_and_utc_dates(): void
    {
        $admin = $this->login();
        $book = $this->book($admin);
        $member = $this->member($admin, '1001');

        $response = $this->postJson('/api/v1/loans', ['member_id' => $member, 'items' => [['book_id' => $book['id']]]], $this->h($admin))
            ->assertCreated()
            ->assertJsonStructure([
                'id', 'member_id', 'loaned_at', 'status',
                'items' => [['id', 'book_id', 'book_title', 'isbn', 'category_name', 'due_at', 'returned_at']],
            ]);

        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', (string) $response->json('loaned_at'));
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', (string) $response->json('items.0.due_at'));
        $this->assertSame(14 * 86400, strtotime((string) $response->json('items.0.due_at')) - strtotime((string) $response->json('loaned_at')));
    }


    public function test_report_requires_both_dates_and_orders_by_times_borrowed(): void
    {
        $admin = $this->login();
        $a = $this->book($admin, ['total_copies' => 5]);
        $b = $this->book($admin, ['title' => 'Cosmos', 'isbn' => '9780000000011', 'category_id' => self::CIENCIA, 'total_copies' => 5]);
        foreach (['1001', '2002'] as $doc) {
            $this->postJson('/api/v1/loans', ['member_id' => $this->member($admin, $doc), 'items' => [['book_id' => $b['id']]]], $this->h($admin))->assertCreated();
        }
        $this->postJson('/api/v1/loans', ['member_id' => $this->member($admin, '3003'), 'items' => [['book_id' => $a['id']]]], $this->h($admin))->assertCreated();
        $today = gmdate('Y-m-d');

        $rows = $this->getJson("/api/v1/reports/most-borrowed?from={$today}&to={$today}", $this->h($admin))
            ->assertOk()->assertJsonPath('from', $today)->assertJsonPath('to', $today)->json('rows');

        $this->assertSame(['Cosmos', 'Cien años de soledad'], array_column($rows, 'book_title'));
        $this->assertSame([2, 1], array_column($rows, 'times_borrowed'));
        $this->assertArrayNotHasKey('member_id', $rows[0], 'el reporte no desglosa por socio');

        $this->assertErrorBody($this->getJson('/api/v1/reports/most-borrowed?from=' . $today, $this->h($admin)), 422, 'VALIDATION_ERROR');
        $this->assertErrorBody($this->getJson('/api/v1/reports/most-borrowed?from=ayer&to=hoy', $this->h($admin)), 422, 'VALIDATION_ERROR');
    }

    public function test_password_never_appears_in_any_response(): void
    {
        $admin = $this->login();

        $login = $this->postJson('/api/v1/auth/login', ['username' => 'admin', 'password' => 'Admin#12345']);
        $created = $this->postJson('/api/v1/users', ['username' => 'luis', 'password' => 'Secreto#123', 'role' => 'librarian'], $this->h($admin));

        foreach ([$login, $created] as $response) {
            $this->assertStringNotContainsString('Admin#12345', (string) $response->getContent());
            $this->assertStringNotContainsString('Secreto#123', (string) $response->getContent());
            $this->assertStringNotContainsString('password', (string) $response->getContent());
        }
    }

    public function test_short_librarian_password_is_422_and_login_body_is_validated(): void
    {
        $admin = $this->login();

        $this->assertErrorBody(
            $this->postJson('/api/v1/users', ['username' => 'luis', 'password' => 'corta', 'role' => 'librarian'], $this->h($admin)),
            422,
            'VALIDATION_ERROR',
        );
        $this->assertErrorBody($this->postJson('/api/v1/auth/login', []), 422, 'VALIDATION_ERROR');
    }
}
