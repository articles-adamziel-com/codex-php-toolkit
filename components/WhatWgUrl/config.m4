PHP_ARG_ENABLE(whatwg_url, whether to enable whatwg_url support,
[  --enable-whatwg_url   Enable WHATWG URL extension], no)

if test "$PHP_WHATWG_URL" != "no"; then
  PHP_REQUIRE_CXX()
  AC_DEFINE([HAVE_WHATWG_URL], 1, [Have WHATWG URL extension])
  PHP_NEW_EXTENSION(whatwg_url, whatwg_url.c ada.cpp, $ext_shared)
fi
