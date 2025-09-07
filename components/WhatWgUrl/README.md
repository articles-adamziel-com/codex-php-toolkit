# WHATWG URL PHP Extension

This extension adds a `whatwg_url` module for PHP that provides parsing and editing of URLs according to the [WHATWG URL specification](https://url.spec.whatwg.org/).  It is powered by the [Ada](https://github.com/ada-url/ada) library and exposes two functions:

- `whatwg_url_parse(string $url): array` – returns the components of a URL.
- `whatwg_url_set_component(string $url, string $component, string $value): string|false` – returns the modified URL or `false` on failure.

## Building

```sh
phpize
./configure --enable-whatwg_url
make
make install
```

## Example

```php
<?php
$url = 'https://user:pass@example.com:8080/path?query#fragment';
print_r(whatwg_url_parse($url));

echo whatwg_url_set_component($url, 'host', 'wordpress.org');
```
