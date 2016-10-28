<?php
/*
 +--------------------------------------------------------------------+
 | CiviCRM version 4.7                                                |
 +--------------------------------------------------------------------+
 | Copyright CiviCRM LLC (c) 2004-2016                                |
 +--------------------------------------------------------------------+
 | This file is a part of CiviCRM.                                    |
 |                                                                    |
 | CiviCRM is free software; you can copy, modify, and distribute it  |
 | under the terms of the GNU Affero General Public License           |
 | Version 3, 19 November 2007 and the CiviCRM Licensing Exception.   |
 |                                                                    |
 | CiviCRM is distributed in the hope that it will be useful, but     |
 | WITHOUT ANY WARRANTY; without even the implied warranty of         |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.               |
 | See the GNU Affero General Public License for more details.        |
 |                                                                    |
 | You should have received a copy of the GNU Affero General Public   |
 | License and the CiviCRM Licensing Exception along                  |
 | with this program; if not, contact CiviCRM LLC                     |
 | at info'AT'civicrm'DOT'org. If you have questions about the        |
 | GNU Affero General Public License or the licensing of CiviCRM,     |
 | see the CiviCRM license FAQ at http://civicrm.org/licensing        |
 +--------------------------------------------------------------------+
 */

/**
 * Class CRM_Contribute_BAO_ContributionPageTest
 * @group headless
 */
class CRM_Contribute_BAO_ContributionPageTest extends CiviUnitTestCase {

  public function setUp() {
    parent::setUp();
    $this->_financialTypeID = 1;
  }

  public function tearDown() {
    CRM_Core_I18n_Schema::makeSinglelingual('en_US');
  }

  /**
   * Create() method (create Contribution Page)
   */
  public function testCreate() {

    $params = array(
      'qfkey' => '9a3ef3c08879ad4c8c109b21c583400e',
      'title' => 'Test Contribution Page',
      'financial_type_id' => $this->_financialTypeID,
      'intro_text' => '',
      'footer_text' => 'Thanks',
      'is_for_organization' => 0,
      'for_organization' => ' I am contributing on behalf of an organization',
      'goal_amount' => '400',
      'is_active' => 1,
      'honor_block_title' => '',
      'honor_block_text' => '',
      'start_date' => '20091022105900',
      'start_date_time' => '10:59AM',
      'end_date' => '19700101000000',
      'end_date_time' => '',
      'is_credit_card_only' => '',
    );

    $contributionpage = CRM_Contribute_BAO_ContributionPage::create($params);

    $this->assertNotNull($contributionpage->id);
    $this->assertType('int', $contributionpage->id);
    $this->callAPISuccess('ContributionPage', 'delete', array('id' => $contributionpage->id));
  }

  /**
   *  test setIsActive() method
   */
  public function testsetIsActive() {

    $params = array(
      'title' => 'Test Contribution Page',
      'financial_type_id' => $this->_financialTypeID,
      'is_active' => 1,
    );

    $contributionpage = CRM_Contribute_BAO_ContributionPage::create($params);
    $id = $contributionpage->id;
    $is_active = 1;
    $pageActive = CRM_Contribute_BAO_ContributionPage::setIsActive($id, $is_active);
    $this->assertEquals($pageActive, TRUE, 'Verify financial types record deletion.');
    $this->callAPISuccess('ContributionPage', 'delete', array('id' => $contributionpage->id));
  }

  /**
   * Test setValues() method
   */
  public function testSetValues() {

    $params = array(
      'title' => 'Test Contribution Page',
      'financial_type_id' => $this->_financialTypeID,
      'is_active' => 1,
    );

    $contributionPage = CRM_Contribute_BAO_ContributionPage::create($params);

    $id = $contributionPage->id;
    $values = array();
    CRM_Contribute_BAO_ContributionPage::setValues($id, $values);

    $this->assertEquals($params['title'], $values['title'], 'Verify contribution title.');
    $this->assertEquals($this->_financialTypeID, $values['financial_type_id'], 'Verify financial types id.');
    $this->assertEquals(1, $values['is_active'], 'Verify contribution is_active value.');
    $this->callAPISuccess('ContributionPage', 'delete', array('id' => $contributionPage->id));
  }

  /**
   * Test copy() method
   */
  public function testcopy() {
    $params = array(
      'qfkey' => '9a3ef3c08879ad4c8c109b21c583400e',
      'title' => 'Test Contribution Page',
      'financial_type_id' => $this->_financialTypeID,
      'intro_text' => '',
      'footer_text' => 'Thanks',
      'is_for_organization' => 0,
      'for_organization' => ' I am contributing on behalf of an organization',
      'goal_amount' => '400',
      'is_active' => 1,
      'honor_block_title' => '',
      'honor_block_text' => '',
      'start_date' => '20091022105900',
      'start_date_time' => '10:59AM',
      'end_date' => '19700101000000',
      'end_date_time' => '',
      'is_credit_card_only' => '',
    );

    $contributionPage = CRM_Contribute_BAO_ContributionPage::create($params);

    $this->callAPISuccess('Setting', 'create', array(
      'lcMessages' => 'en_US',
      'languageLimit' => array(
        'en_US' => 1,
      ),
    ));

    CRM_Core_I18n_Schema::makeMultilingual('en_US');

    global $dbLocale;
    $dbLocale = '_en_US';

    CRM_Core_I18n_Schema::addLocale('fr_CA', 'en_US');

    $this->callAPISuccess('Setting', 'create', array(
      'languageLimit' => array(
        'en_US',
        'fr_CA',
      ),
    ));
    $table = 'civicrm_contribution_page';
    $config = CRM_Core_Config::singleton();
    $locale = 'fr_CA';
    $columns = CRM_Core_I18n_SchemaStructure::columns();
    if (!empty($columns)) {
      $multilingualFields = array_keys($columns[$table]);
    }
    $updateFields = array();
    foreach ($multilingualFields as $field) {
      $expectedValues["{$field}_{$locale}"] = "Test {$field}";
      $updateFields[] = "`{$field}_{$locale}` = 'Test {$field}'";
    }
    $params = array(1 => array($contributionPage->id, 'Integer'));

    $query = "UPDATE {$table} SET " . implode(', ', $updateFields) . " WHERE id = %1";
    CRM_Core_DAO::singleValueQuery($query, $params, TRUE, FALSE);

    $copyContributionPage = CRM_Contribute_BAO_ContributionPage::copy($contributionPage->id);
    $this->assertEquals($copyContributionPage->financial_type_id, $this->_financialTypeID, 'Check for Financial type id.');
    $this->assertEquals($copyContributionPage->goal_amount, 400, 'Check for goal amount.');

    // Assert if multilingual fields are correctly updated.
    $queryParams = array(1 => array($copyContributionPage->id, 'Integer'));
    $cols = implode(', ', array_keys($expectedValues));
    $query = "SELECT {$cols} FROM {$table} WHERE id = %1";
    $dao = CRM_Core_DAO::executeQuery($query, $queryParams, TRUE, NULL, FALSE, FALSE);

    while ($dao->fetch()) {
      foreach ($expectedValues as $field => $val) {
        $this->assertEquals($dao->$field, $expectedValues[$field], 'Check for Multilingual values.');
      }
    }
    $this->callAPISuccess('ContributionPage', 'delete', array('id' => $contributionPage->id));
    $this->callAPISuccess('ContributionPage', 'delete', array('id' => $copyContributionPage->id));
  }

}
