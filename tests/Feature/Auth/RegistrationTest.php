test('public registration route is disabled', function () {
    $response = $this->get('/register');

    $response->assertNotFound();
});
