import __main__
#from selenium.webdriver.firefox.webdriver import WebDriver
from selenium.webdriver.phantomjs.webdriver import WebDriver
from os import getenv, makedirs
from os.path import basename, dirname, exists

def get_url(path):
    """Build full URL to test server based on path and env vars"""
    return 'http://%(host)s:%(port)s/%(path)s' % {
        'host': getenv('TEST_WEB_SERVER_HOST', 'http://localhost'),
        'port': getenv('TEST_WEB_SERVER_PORT', 8000),
        'path': path
    }


def get_sreenshot_path(suffix):
    path = '%(base)s/%(test)s_%(suffix)s.png' % {
        'base': getenv('TEST_SCREENSHOT_PATH', '/tmp'),
        'test': basename(__main__.__file__).strip(".py"),
        'suffix': suffix
    }
    if not exists(dirname(path)):
        makedirs(dirname(path))
    return path


def create_webdriver():
    wd = WebDriver()
    wd.implicitly_wait(60)
    wd.set_window_size(1400,1000)
    return wd
