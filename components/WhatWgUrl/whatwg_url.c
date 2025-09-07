#include "php.h"
#include "php_whatwg_url.h"
#include "ada_c.h"
#include <string.h>

static PHP_FUNCTION(whatwg_url_parse);
static PHP_FUNCTION(whatwg_url_set_component);

static const zend_function_entry whatwg_url_functions[] = {
    PHP_FE(whatwg_url_parse, NULL)
    PHP_FE(whatwg_url_set_component, NULL)
    PHP_FE_END
};

zend_module_entry whatwg_url_module_entry = {
    STANDARD_MODULE_HEADER,
    "whatwg_url",
    whatwg_url_functions,
    NULL,
    NULL,
    NULL,
    NULL,
    NULL,
    PHP_WHATWG_URL_VERSION,
    STANDARD_MODULE_PROPERTIES
};

#ifdef COMPILE_DL_WHATWG_URL
# ifdef ZTS
ZEND_TSRMLS_CACHE_DEFINE()
# endif
ZEND_GET_MODULE(whatwg_url)
#endif

static PHP_FUNCTION(whatwg_url_parse)
{
    char *input;
    size_t input_len;
    if (zend_parse_parameters(ZEND_NUM_ARGS(), "s", &input, &input_len) == FAILURE) {
        RETURN_THROWS();
    }

    ada_url url = ada_parse(input, input_len);
    if (!url || !ada_is_valid(url)) {
        if (url) {
            ada_free(url);
        }
        RETURN_FALSE;
    }

    array_init(return_value);

    ada_string s;
    s = ada_get_protocol(url);
    add_assoc_stringl(return_value, "protocol", (char *)s.data, s.length);
    s = ada_get_username(url);
    add_assoc_stringl(return_value, "username", (char *)s.data, s.length);
    s = ada_get_password(url);
    add_assoc_stringl(return_value, "password", (char *)s.data, s.length);
    s = ada_get_host(url);
    add_assoc_stringl(return_value, "host", (char *)s.data, s.length);
    s = ada_get_port(url);
    add_assoc_stringl(return_value, "port", (char *)s.data, s.length);
    s = ada_get_pathname(url);
    add_assoc_stringl(return_value, "pathname", (char *)s.data, s.length);
    s = ada_get_search(url);
    add_assoc_stringl(return_value, "search", (char *)s.data, s.length);
    s = ada_get_hash(url);
    add_assoc_stringl(return_value, "hash", (char *)s.data, s.length);

    ada_free(url);
}

static bool set_component(ada_url url, const char *component, size_t component_len, const char *value, size_t value_len)
{
    if (component_len == strlen("protocol") && strcasecmp(component, "protocol") == 0) {
        return ada_set_protocol(url, value, value_len);
    }
    if (component_len == strlen("username") && strcasecmp(component, "username") == 0) {
        return ada_set_username(url, value, value_len);
    }
    if (component_len == strlen("password") && strcasecmp(component, "password") == 0) {
        return ada_set_password(url, value, value_len);
    }
    if (component_len == strlen("host") && strcasecmp(component, "host") == 0) {
        return ada_set_host(url, value, value_len);
    }
    if (component_len == strlen("hostname") && strcasecmp(component, "hostname") == 0) {
        return ada_set_hostname(url, value, value_len);
    }
    if (component_len == strlen("port") && strcasecmp(component, "port") == 0) {
        return ada_set_port(url, value, value_len);
    }
    if (component_len == strlen("pathname") && strcasecmp(component, "pathname") == 0) {
        return ada_set_pathname(url, value, value_len);
    }
    if (component_len == strlen("search") && strcasecmp(component, "search") == 0) {
        ada_set_search(url, value, value_len);
        return true;
    }
    if (component_len == strlen("hash") && strcasecmp(component, "hash") == 0) {
        ada_set_hash(url, value, value_len);
        return true;
    }
    return false;
}

static PHP_FUNCTION(whatwg_url_set_component)
{
    char *input, *component, *value;
    size_t input_len, component_len, value_len;

    ZEND_PARSE_PARAMETERS_START(3, 3)
        Z_PARAM_STRING(input, input_len)
        Z_PARAM_STRING(component, component_len)
        Z_PARAM_STRING(value, value_len)
    ZEND_PARSE_PARAMETERS_END();

    ada_url url = ada_parse(input, input_len);
    if (!url || !ada_is_valid(url)) {
        if (url) {
            ada_free(url);
        }
        RETURN_FALSE;
    }

    bool ok = set_component(url, component, component_len, value, value_len);
    if (!ok) {
        ada_free(url);
        php_error_docref(NULL, E_WARNING, "Unknown component '%s'", component);
        RETURN_FALSE;
    }

    ada_string href = ada_get_href(url);
    RETVAL_STRINGL(href.data, href.length);
    ada_free(url);
}
