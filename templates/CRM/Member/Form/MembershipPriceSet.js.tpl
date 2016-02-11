{*
 +--------------------------------------------------------------------+
 | CiviCRM version 4.7                                                |
 +--------------------------------------------------------------------+
 | Copyright CiviCRM LLC (c) 2004-2015                                |
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
 | at info[AT]civicrm[DOT]org. If you have questions about the        |
 | GNU Affero General Public License or the licensing of CiviCRM,     |
 | see the CiviCRM license FAQ at http://civicrm.org/licensing        |
 +--------------------------------------------------------------------+
*}
{literal}
  var customDataType = '{/literal}{$customDataType}{literal}';

  // load form during form rule.
  {/literal}{if $buildPriceSet}{literal}
  cj( "#totalAmountORPriceSet" ).hide( );
  cj( "#mem_type_id" ).hide( );
  cj('#total_amount').attr("readonly", true);
  cj( "#num_terms_row" ).hide( );
  cj(".crm-membership-form-block-financial_type_id-mode").hide();
  {/literal}{/if}{literal}

  function buildAmount( priceSetId ) {
    if ( !priceSetId ) {
      priceSetId = cj("#price_set_id").val( );
    }
    var fname = '#priceset';
    if ( !priceSetId ) {
      cj('#membership_type_id_1').val(0);
      CRM.buildCustomData(customDataType, 'null' );

      // hide price set fields.
      cj( fname ).hide( );

      // show/hide price set amount and total amount.
      cj( "#mem_type_id").show( );
      var choose = "{/literal}{ts}Choose price set{/ts}{literal}";
      cj("#price_set_id option[value='']").html( choose );
      cj( "#totalAmountORPriceSet" ).show( );
      cj('#total_amount').removeAttr("readonly");
      cj( "#num_terms_row").show( );
      cj(".crm-membership-form-block-financial_type_id-mode").show();

      {/literal}{if $allowAutoRenew}{literal}
      cj('#autoRenew').hide();
      var autoRenew = cj("#auto_renew");
      autoRenew.removeAttr( 'readOnly' );
      autoRenew.prop('checked', false );
      {/literal}{/if}{literal}
      return;
    }

    cj( "#total_amount" ).val( '' );
    cj('#total_amount').attr("readonly", true);

    var dataUrl = {/literal}"{crmURL h=0 q='snippet=4'}"{literal} + '&priceSetId=' + priceSetId;

    var response = cj.ajax({
      url: dataUrl,
      async: false
    }).responseText;

    cj( fname ).show( ).html( response );
    // freeze total amount text field.
    cj( "#totalAmountORPriceSet" ).hide( );
    cj( "#mem_type_id" ).hide( );
    var manual = "{/literal}{ts}Manual membership and price{/ts}{literal}";
    cj("#price_set_id option[value='']").html( manual );
    cj( "#num_terms_row" ).hide( );
    cj(".crm-membership-form-block-financial_type_id-mode").hide();
  }

  var lastMembershipTypes = [];
  var optionsMembershipTypes = [];

  // function to load custom data for selected membership types through priceset
  function processMembershipPriceset( membershipValues, autoRenewOption, reload ) {
    var currentMembershipType = [];
    var count = 0;
    var loadCustomData = 0;
    if ( membershipValues ) {
      optionsMembershipTypes = membershipValues;
    }

    if ( reload ) {
      lastMembershipTypes = [];
      {/literal}{if $allowAutoRenew}{literal}
      cj('#autoRenew').hide();
      var autoRenew = cj("#auto_renew");
      autoRenew.removeAttr( 'readOnly' );
      autoRenew.prop('checked', false );
      if ( autoRenewOption == 1 ) {
        cj('#autoRenew').show();
      }
      else if ( autoRenewOption == 2 ) {
        autoRenew.attr( 'readOnly', true );
        autoRenew.prop('checked',  true );
        cj('#autoRenew').show();
      }
      {/literal}{/if}{literal}
    }
    cj("input,#priceset select,#priceset").each(function () {
      if ( cj(this).attr('price') ) {
        switch( cj(this).attr('type') ) {
          case 'checkbox':
            if ( cj(this).prop('checked') ) {
              eval( 'var option = ' + cj(this).attr('price') ) ;
              var ele = option[0];
              var memTypeId = optionsMembershipTypes[ele];
              if ( memTypeId && cj.inArray(optionsMembershipTypes[ele], currentMembershipType) == -1 ) {
                currentMembershipType[count] = memTypeId;
                count++;
              }
            }
            if ( reload ) {
              cj(this).click( function( ) {
                processMembershipPriceset();
              });
            }
            break;

          case 'radio':
            if ( cj(this).prop('checked') && cj(this).val() ) {
              var memTypeId = optionsMembershipTypes[cj(this).val()];
              if ( memTypeId && cj.inArray(memTypeId, currentMembershipType) == -1 ) {
                currentMembershipType[count] = memTypeId;
                count++;
              }
            }
            if ( reload ) {
              cj(this).click( function( ) {
                processMembershipPriceset();
              });
            }
            break;

          case 'select-one':
            if ( cj(this).val( ) ) {
              var memTypeId = optionsMembershipTypes[cj(this).val()];
              if ( memTypeId && cj.inArray(memTypeId, currentMembershipType) == -1 ) {
                currentMembershipType[count] = memTypeId;
                count++;
              }
            }
            if ( reload ) {
              cj(this).change( function( ) {
                processMembershipPriceset();
              });
            }
            break;
        }
      }
    });

    for( i in currentMembershipType ) {
      if ( cj.inArray(currentMembershipType[i], lastMembershipTypes) == -1 ) {
        loadCustomData = 1;
        break;
      }
    }

    if ( !loadCustomData ) {
      for( i in lastMembershipTypes) {
        if ( cj.inArray(lastMembershipTypes[i], currentMembershipType) == -1 ) {
          loadCustomData = 1;
          break;
        }
      }
    }

    lastMembershipTypes = currentMembershipType;

    // load custom data only if change in membership type selection
    if ( !loadCustomData ) {
      return;
    }
    subTypeNames = currentMembershipType.join(',');
    if ( subTypeNames.length < 1 ) {
      subTypeNames = 'null';
    }
    CRM.buildCustomData( customDataType, subTypeNames );
  }

  function enableAmountSection( setContributionType ) {
    if ( !cj('#record_contribution').prop('checked') ) {
      cj('#record_contribution').click( );
      cj('#recordContribution').show( );
    }
    if ( setContributionType ) {
    cj('#financial_type_id').val(setContributionType);
    }
  }
{/literal}
