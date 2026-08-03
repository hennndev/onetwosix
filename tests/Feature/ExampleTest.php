it('redirects root to login route', function () {
    $response = $this->get('/');

    $response->assertRedirect(route('login'));
});
