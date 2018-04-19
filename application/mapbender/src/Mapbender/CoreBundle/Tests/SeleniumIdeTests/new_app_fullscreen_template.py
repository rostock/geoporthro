# -*- coding: utf-8 -*-

from lib.user import login
from lib.logout import logout
from lib.utils import get_sreenshot_path, create_webdriver  # Changed


success = True
wd = create_webdriver()


def is_alert_present(wd):
    try:
        wd.switch_to_alert().text
        return True
    except:
        return False

try:
    login(wd)
    wd.find_element_by_link_text("New application").click()
    wd.find_element_by_css_selector("div.dropdownValue.iconDown").click()
    wd.find_element_by_css_selector("li.item-2").click()
    wd.find_element_by_id("application_title").send_keys("testing fullscreen template")
    wd.find_element_by_id("application_slug").send_keys("testing_fullscreen_template")
    wd.find_element_by_id("application_description").send_keys("run a test to create a new application based on the fullscreen template")
    wd.find_element_by_css_selector("input.button").click()
    if not ("testing fullscreen template" in wd.find_element_by_tag_name("html").text):
        raise Exception("find_element_by_tag_name failed: testing fullscreen template")
    logout(wd)
except Exception as e:  # Changed ff
    wd.save_screenshot(get_sreenshot_path('error'))
    wd.quit()
    raise e
finally:
    wd.quit()
    if not success:
        raise Exception("Test failed.")
