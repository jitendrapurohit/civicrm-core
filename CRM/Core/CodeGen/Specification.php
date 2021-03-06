<?php

/**
 * Read the schema specification and parse into internal data structures
 */
class CRM_Core_CodeGen_Specification {
  public $tables;
  public $database;

  protected $classNames;

  /**
   * Read and parse.
   *
   * @param $schemaPath
   * @param string $buildVersion which version of the schema to build
   */
  function parse($schemaPath, $buildVersion) {
    $this->buildVersion = $buildVersion;

    echo "Parsing schema description ".$schemaPath."\n";
    $dbXML = CRM_Core_CodeGen_Util_Xml::parse($schemaPath);
    // print_r( $dbXML );

    echo "Extracting database information\n";
    $this->database = &$this->getDatabase($dbXML);
    // print_r( $this->database );

    $this->classNames = array();

    # TODO: peel DAO-specific stuff out of getTables, and spec reading into its own class
    echo "Extracting table information\n";
    $this->tables = $this->getTables($dbXML, $this->database);

    $this->resolveForeignKeys($this->tables, $this->classNames);
    $this->tables = $this->orderTables($this->tables);

    // add archive tables here
    $archiveTables = array( );
    foreach ($this->tables as $name => $table ) {
      if ( $table['archive'] == 'true' ) {
        $name = 'archive_' . $table['name'];
        $table['name'] = $name;
        $table['archive'] = 'false';
        if ( isset($table['foreignKey']) ) {
          foreach ($table['foreignKey'] as $fkName => $fkValue) {
            if ($this->tables[$fkValue['table']]['archive'] == 'true') {
              $table['foreignKey'][$fkName]['table'] = 'archive_' . $table['foreignKey'][$fkName]['table'];
              $table['foreignKey'][$fkName]['uniqName'] =
                str_replace( 'FK_', 'FK_archive_', $table['foreignKey'][$fkName]['uniqName'] );
            }
          }
          $archiveTables[$name] = $table;
        }
      }
    }
  }

  function &getDatabase(&$dbXML) {
    $database = array('name' => trim((string ) $dbXML->name));

    $attributes = '';
    $this->checkAndAppend($attributes, $dbXML, 'character_set', 'DEFAULT CHARACTER SET ', '');
    $this->checkAndAppend($attributes, $dbXML, 'collate', 'COLLATE ', '');
    $database['attributes'] = $attributes;

    $tableAttributes_modern = $tableAttributes_simple = '';
    $this->checkAndAppend($tableAttributes_modern, $dbXML, 'table_type', 'ENGINE=', '');
    $this->checkAndAppend($tableAttributes_simple, $dbXML, 'table_type', 'TYPE=', '');
    $database['tableAttributes_modern'] = trim($tableAttributes_modern . ' ' . $attributes);
    $database['tableAttributes_simple'] = trim($tableAttributes_simple);

    $database['comment'] = $this->value('comment', $dbXML, '');

    return $database;
  }

  function getTables($dbXML, &$database) {
    $tables = array();
    foreach ($dbXML->tables as $tablesXML) {
      foreach ($tablesXML->table as $tableXML) {
        if ($this->value('drop', $tableXML, 0) > 0 and $this->value('drop', $tableXML, 0) <= $this->buildVersion) {
          continue;
        }

        if ($this->value('add', $tableXML, 0) <= $this->buildVersion) {
          $this->getTable($tableXML, $database, $tables);
        }
      }
    }

    return $tables;
  }

  function resolveForeignKeys(&$tables, &$classNames) {
    foreach (array_keys($tables) as $name) {
      $this->resolveForeignKey($tables, $classNames, $name);
    }
  }

  function resolveForeignKey(&$tables, &$classNames, $name) {
    if (!array_key_exists('foreignKey', $tables[$name])) {
      return;
    }

    foreach (array_keys($tables[$name]['foreignKey']) as $fkey) {
      $ftable = $tables[$name]['foreignKey'][$fkey]['table'];
      if (!array_key_exists($ftable, $classNames)) {
        echo "$ftable is not a valid foreign key table in $name\n";
        continue;
      }
      $tables[$name]['foreignKey'][$fkey]['className'] = $classNames[$ftable];
      $tables[$name]['foreignKey'][$fkey]['fileName'] = str_replace('_', '/', $classNames[$ftable]) . '.php';
      $tables[$name]['fields'][$fkey]['FKClassName'] = $classNames[$ftable];
    }
  }

  function orderTables(&$tables) {
    $ordered = array();

    while (!empty($tables)) {
      foreach (array_keys($tables) as $name) {
        if ($this->validTable($tables, $ordered, $name)) {
          $ordered[$name] = $tables[$name];
          unset($tables[$name]);
        }
      }
    }
    return $ordered;
  }

  function validTable(&$tables, &$valid, $name) {
    if (!array_key_exists('foreignKey', $tables[$name])) {
      return TRUE;
    }

    foreach (array_keys($tables[$name]['foreignKey']) as $fkey) {
      $ftable = $tables[$name]['foreignKey'][$fkey]['table'];
      if (!array_key_exists($ftable, $valid) && $ftable !== $name) {
        return FALSE;
      }
    }
    return TRUE;
  }

  function getTable($tableXML, &$database, &$tables) {
    $name = trim((string ) $tableXML->name);
    $klass = trim((string ) $tableXML->class);
    $base = $this->value('base', $tableXML);
    $sourceFile = "xml/schema/{$base}/{$klass}.xml";
    $daoPath = "{$base}/DAO/";
    $pre = str_replace('/', '_', $daoPath);
    $this->classNames[$name] = $pre . $klass;

    $localizable = FALSE;
    foreach ($tableXML->field as $fieldXML) {
      if ($fieldXML->localizable) {
        $localizable = TRUE;
        break;
      }
    }

    $table = array(
      'name' => $name,
      'base' => $daoPath,
      'sourceFile' => $sourceFile,
      'fileName' => $klass . '.php',
      'objectName' => $klass,
      'labelName' => substr($name, 8),
      'className' => $this->classNames[$name],
      'attributes_simple' => trim($database['tableAttributes_simple']),
      'attributes_modern' => trim($database['tableAttributes_modern']),
      'comment' => $this->value('comment', $tableXML),
      'localizable' => $localizable,
      'log' => $this->value('log', $tableXML, 'false'),
      'archive' => $this->value('archive', $tableXML, 'false'),
    );

    $fields = array();
    foreach ($tableXML->field as $fieldXML) {
      if ($this->value('drop', $fieldXML, 0) > 0 and $this->value('drop', $fieldXML, 0) <= $this->buildVersion) {
        continue;
      }

      if ($this->value('add', $fieldXML, 0) <= $this->buildVersion) {
        $this->getField($fieldXML, $fields);
      }
    }

    $table['fields'] = &$fields;

    if ($this->value('primaryKey', $tableXML)) {
      $this->getPrimaryKey($tableXML->primaryKey, $fields, $table);
    }

    // some kind of refresh?
    CRM_Core_Config::singleton(FALSE);
    if ($this->value('index', $tableXML)) {
      $index = array();
      foreach ($tableXML->index as $indexXML) {
        if ($this->value('drop', $indexXML, 0) > 0 and $this->value('drop', $indexXML, 0) <= $this->buildVersion) {
          continue;
        }

        $this->getIndex($indexXML, $fields, $index);
      }
      $table['index'] = &$index;
    }

    if ($this->value('foreignKey', $tableXML)) {
      $foreign = array();
      foreach ($tableXML->foreignKey as $foreignXML) {
        // print_r($foreignXML);

        if ($this->value('drop', $foreignXML, 0) > 0 and $this->value('drop', $foreignXML, 0) <= $this->buildVersion) {
          continue;
        }
        if ($this->value('add', $foreignXML, 0) <= $this->buildVersion) {
          $this->getForeignKey($foreignXML, $fields, $foreign, $name);
        }
      }
      $table['foreignKey'] = &$foreign;
    }

    if ($this->value('dynamicForeignKey', $tableXML)) {
      $dynamicForeign = array();
      foreach ($tableXML->dynamicForeignKey as $foreignXML) {
        if ($this->value('drop', $foreignXML, 0) > 0 and $this->value('drop', $foreignXML, 0) <= $this->buildVersion) {
          continue;
        }
        if ($this->value('add', $foreignXML, 0) <= $this->buildVersion) {
          $this->getDynamicForeignKey($foreignXML, $dynamicForeign, $name);
        }
      }
      $table['dynamicForeignKey'] = $dynamicForeign;
    }

    $tables[$name] = &$table;
    return;
  }

  function getField(&$fieldXML, &$fields) {
    $name  = trim((string ) $fieldXML->name);
    $field = array('name' => $name, 'localizable' => $fieldXML->localizable);
    $type  = (string ) $fieldXML->type;
    switch ($type) {
      case 'varchar':
      case 'char':
        $field['length'] = (int) $fieldXML->length;
        $field['sqlType'] = "$type({$field['length']})";
        $field['phpType'] = 'string';
        $field['crmType'] = 'CRM_Utils_Type::T_STRING';
        $field['size'] = $this->getSize($fieldXML);
        break;

      case 'text':
        $field['sqlType'] = $field['phpType'] = $type;
        $field['crmType'] = 'CRM_Utils_Type::T_' . strtoupper($type);
        // CRM-13497 see fixme below
        $field['rows'] = isset($fieldXML->html) ? $this->value('rows', $fieldXML->html) : NULL;
        $field['cols'] = isset($fieldXML->html) ? $this->value('cols', $fieldXML->html) : NULL;
        break;
        break;

      case 'datetime':
        $field['sqlType'] = $field['phpType'] = $type;
        $field['crmType'] = 'CRM_Utils_Type::T_DATE + CRM_Utils_Type::T_TIME';
        break;

      case 'boolean':
        // need this case since some versions of mysql do not have boolean as a valid column type and hence it
        // is changed to tinyint. hopefully after 2 yrs this case can be removed.
        $field['sqlType'] = 'tinyint';
        $field['phpType'] = $type;
        $field['crmType'] = 'CRM_Utils_Type::T_' . strtoupper($type);
        break;

      case 'decimal':
        $length = $fieldXML->length ? $fieldXML->length : '20,2';
        $field['sqlType'] = 'decimal(' . $length . ')';
        $field['phpType'] = 'float';
        $field['crmType'] = 'CRM_Utils_Type::T_MONEY';
        $field['precision'] = $length;
        break;

      case 'float':
        $field['sqlType'] = 'double';
        $field['phpType'] = 'float';
        $field['crmType'] = 'CRM_Utils_Type::T_FLOAT';
        break;

      default:
        $field['sqlType'] = $field['phpType'] = $type;
        if ($type == 'int unsigned') {
          $field['crmType'] = 'CRM_Utils_Type::T_INT';
        }
        else {
          $field['crmType'] = 'CRM_Utils_Type::T_' . strtoupper($type);
        }
        break;
    }

    $field['required'] = $this->value('required', $fieldXML);
    $field['collate']  = $this->value('collate', $fieldXML);
    $field['comment']  = $this->value('comment', $fieldXML);
    $field['default']  = $this->value('default', $fieldXML);
    $field['import']   = $this->value('import', $fieldXML);
    if ($this->value('export', $fieldXML)) {
      $field['export'] = $this->value('export', $fieldXML);
    }
    else {
      $field['export'] = $this->value('import', $fieldXML);
    }
    $field['rule'] = $this->value('rule', $fieldXML);
    $field['title'] = $this->value('title', $fieldXML);
    if (!$field['title']) {
      $field['title'] = $this->composeTitle($name);
    }
    $field['headerPattern'] = $this->value('headerPattern', $fieldXML);
    $field['dataPattern'] = $this->value('dataPattern', $fieldXML);
    $field['uniqueName'] = $this->value('uniqueName', $fieldXML);
    $field['html'] = $this->value('html', $fieldXML);
    if (!empty($field['html'])) {
      $validOptions = array(
        'type',
        /* Fixme: prior to CRM-13497 these were in a flat structure
        // CRM-13497 moved them to be nested within 'html' but there's no point
        // making that change in the DAOs right now since we are in the process of
        // moving to docrtine anyway.
        // So translating from nested xml back to flat structure for now.
        'rows',
        'cols',
        'size', */
      );
      $field['html'] = array();
      foreach ($validOptions as $htmlOption) {
        if(!empty($fieldXML->html->$htmlOption)){
          $field['html'][$htmlOption] = $this->value($htmlOption, $fieldXML->html);
        }
      }
    }
    $field['pseudoconstant'] = $this->value('pseudoconstant', $fieldXML);
    if(!empty($field['pseudoconstant'])){
      //ok this is a bit long-winded but it gets there & is consistent with above approach
      $field['pseudoconstant'] = array();
      $validOptions = array(
        // Fields can specify EITHER optionGroupName OR table, not both
        // (since declaring optionGroupName means we are using the civicrm_option_value table)
        'optionGroupName',
        'table',
        // If table is specified, keyColumn and labelColumn are also required
        'keyColumn',
        'labelColumn',
        // Non-translated machine name for programmatic lookup. Defaults to 'name' if that column exists
        'nameColumn',
        // Where clause snippet (will be joined to the rest of the query with AND operator)
        'condition',
        // callback funtion incase of static arrays
        'callback',
      );
      foreach ($validOptions as $pseudoOption) {
        if(!empty($fieldXML->pseudoconstant->$pseudoOption)){
          $field['pseudoconstant'][$pseudoOption] = $this->value($pseudoOption, $fieldXML->pseudoconstant);
        }
      }
      // For now, fields that have option lists that are not in the db can simply
      // declare an empty pseudoconstant tag and we'll add this placeholder.
      // That field's BAO::buildOptions fn will need to be responsible for generating the option list
      if (empty($field['pseudoconstant'])) {
        $field['pseudoconstant'] = 'not in database';
      }
    }
    $fields[$name] = &$field;
  }

  function composeTitle($name) {
    $names = explode('_', strtolower($name));
    $title = '';
    for ($i = 0; $i < count($names); $i++) {
      if ($names[$i] === 'id' || $names[$i] === 'is') {
        // id's do not get titles
        return NULL;
      }

      if ($names[$i] === 'im') {
        $names[$i] = 'IM';
      }
      else {
        $names[$i] = ucfirst(trim($names[$i]));
      }

      $title = $title . ' ' . $names[$i];
    }
    return trim($title);
  }

  function getPrimaryKey(&$primaryXML, &$fields, &$table) {
    $name = trim((string ) $primaryXML->name);

    /** need to make sure there is a field of type name */
    if (!array_key_exists($name, $fields)) {
        echo "primary key $name in $table->name does not have a field definition, ignoring\n";
      return;
    }

    // set the autoincrement property of the field
    $auto = $this->value('autoincrement', $primaryXML);
    $fields[$name]['autoincrement'] = $auto;
    $primaryKey = array(
      'name' => $name,
      'autoincrement' => $auto,
    );
    $table['primaryKey'] = &$primaryKey;
  }

  function getIndex(&$indexXML, &$fields, &$indices) {
    //echo "\n\n*******************************************************\n";
    //echo "entering getIndex\n";

    $index = array();
    // empty index name is fine
    $indexName      = trim((string)$indexXML->name);
    $index['name']  = $indexName;
    $index['field'] = array();

    // populate fields
    foreach ($indexXML->fieldName as $v) {
      $fieldName = (string)($v);
      $length = (string)($v['length']);
      if (strlen($length) > 0) {
        $fieldName = "$fieldName($length)";
      }
      $index['field'][] = $fieldName;
    }

    $index['localizable'] = FALSE;
    foreach ($index['field'] as $fieldName) {
      if (isset($fields[$fieldName]) and $fields[$fieldName]['localizable']) {
        $index['localizable'] = TRUE;
        break;
      }
    }

    // check for unique index
    if ($this->value('unique', $indexXML)) {
      $index['unique'] = TRUE;
    }

    //echo "\$index = \n";
    //print_r($index);

    // field array cannot be empty
    if (empty($index['field'])) {
      echo "No fields defined for index $indexName\n";
      return;
    }

    // all fieldnames have to be defined and should exist in schema.
    foreach ($index['field'] as $fieldName) {
      if (!$fieldName) {
        echo "Invalid field defination for index $indexName\n";
        return;
      }
      $parenOffset = strpos($fieldName, '(');
      if ($parenOffset > 0) {
        $fieldName = substr($fieldName, 0, $parenOffset);
      }
      if (!array_key_exists($fieldName, $fields)) {
        echo "Table does not contain $fieldName\n";
        print_r($fields);
        exit();
      }
    }
    $indices[$indexName] = &$index;
  }

  function getForeignKey(&$foreignXML, &$fields, &$foreignKeys, &$currentTableName) {
    $name = trim((string ) $foreignXML->name);

    /** need to make sure there is a field of type name */
    if (!array_key_exists($name, $fields)) {
        echo "foreign $name in $currentTableName does not have a field definition, ignoring\n";
      return;
    }

    /** need to check for existence of table and key **/
    $table = trim($this->value('table', $foreignXML));
    $foreignKey = array(
      'name' => $name,
      'table' => $table,
      'uniqName' => "FK_{$currentTableName}_{$name}",
      'key' => trim($this->value('key', $foreignXML)),
      'import' => $this->value('import', $foreignXML, FALSE),
      'export' => $this->value('import', $foreignXML, FALSE),
      // we do this matching in a seperate phase (resolveForeignKeys)
      'className' => NULL,
      'onDelete' => $this->value('onDelete', $foreignXML, FALSE),
    );
    $foreignKeys[$name] = &$foreignKey;
  }

  function getDynamicForeignKey(&$foreignXML, &$dynamicForeignKeys) {
    $foreignKey = array(
      'idColumn' => trim($foreignXML->idColumn),
      'typeColumn' => trim($foreignXML->typeColumn),
      'key' => trim($this->value('key', $foreignXML)),
    );
    $dynamicForeignKeys[] = $foreignKey;
  }

  protected function value($key, &$object, $default = NULL) {
    if (isset($object->$key)) {
      return (string ) $object->$key;
    }
    return $default;
  }

  protected function checkAndAppend(&$attributes, &$object, $name, $pre = NULL, $post = NULL) {
    if (!isset($object->$name)) {
      return;
    }

    $value = $pre . trim($object->$name) . $post;
    $this->append($attributes, ' ', trim($value));
  }

  protected function append(&$str, $delim, $name) {
    if (empty($name)) {
      return;
    }

    if (is_array($name)) {
      foreach ($name as $n) {
        if (empty($n)) {
          continue;
        }
        if (empty($str)) {
          $str = $n;
        }
        else {
          $str .= $delim . $n;
        }
      }
    }
    else {
      if (empty($str)) {
        $str = $name;
      }
      else {
        $str .= $delim . $name;
      }
    }
  }

  /**
   * Sets the size property of a textfield
   * See constants defined in CRM_Utils_Type for possible values
   */
  protected function getSize($fieldXML) {
    // Extract from <size> tag if supplied
    if (!empty($fieldXML->html) && $this->value('size', $fieldXML->html)) {
      $const = 'CRM_Utils_Type::' . strtoupper($fieldXML->html->size);
      if (defined($const)) {
        return $const;
      }
    }
    // Infer from <length> tag if <size> was not explicitly set or was invalid

    // This map is slightly different from CRM_Core_Form_Renderer::$_sizeMapper
    // Because we usually want fields to render as smaller than their maxlength
    $sizes = array(
      2 => 'TWO',
      4 => 'FOUR',
      6 => 'SIX',
      8 => 'EIGHT',
      16 => 'TWELVE',
      32 => 'MEDIUM',
      64 => 'BIG',
    );
    foreach ($sizes as $length => $name) {
      if ($fieldXML->length <= $length) {
        return "CRM_Utils_Type::$name";
      }
    }
    return 'CRM_Utils_Type::HUGE';
  }
}
