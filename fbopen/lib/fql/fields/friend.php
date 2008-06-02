<?php

/******BEGIN LICENSE BLOCK*******
* 
* Common Public Attribution License Version 1.0.
*
* The contents of this file are subject to the Common Public Attribution 
* License Version 1.0 (the "License") you may not use this file except in 
* compliance with the License. You may obtain a copy of the License at
* http://developers.facebook.com/fbopen/cpal.html. The License is based 
* on the Mozilla Public License Version 1.1 but Sections 14 and 15 have 
* been added to cover use of software over a computer network and provide 
* for limited attribution for the Original Developer. In addition, Exhibit A 
* has been modified to be consistent with Exhibit B.
* Software distributed under the License is distributed on an "AS IS" basis, 
* WITHOUT WARRANTY OF ANY KIND, either express or implied. See the License 
* for the specific language governing rights and limitations under the License.
* The Original Code is Facebook Open Platform.
* The Original Developer is the Initial Developer.
* The Initial Developer of the Original Code is Facebook, Inc.  All portions 
* of the code written by Facebook, Inc are 
* Copyright 2006-2008 Facebook, Inc. All Rights Reserved.
*
*
********END LICENSE BLOCK*********/


// Facebook Copyright 2006 - 2008

class FQLFriend1 extends _FQLBaseField {
  /**
   * Evaluates the value of the uid1 field given an FQLFriendTable
   * id value, which is of the form uid1:uid2.  Hence this function
   * will return the value before the colon.
   */
  public function evaluate($id) {
    $id_arr = explode(':', $id);
    return $id_arr[0];
  }

  /**
   * Given a value for uid1, this function returns the query
   * expression that expresses this constraint (that uid1 == $value).
   * This query takes the form '$value:'.
   */
  public function get_query($value) {
    $value = (int)$value;
    if ($value <= 0) return null;
    return "$value:";
  }
}

class FQLFriend2 extends _FQLBaseField {
  /**
   * Evaluates the value of the uid2 field given an FQLFriendTable
   * id value, which is of the form uid1:uid2.  Hence this function
   * will return the value after the colon.
   */
  public function evaluate($id) {
    $id_arr = explode(':', $id);
    return $id_arr[1];
  }

  /**
   * Given a value for uid2, this function returns the query
   * expression that expresses this constraint (that uid2 == $value).
   * This query takes the form ':$value'.
   */
  public function get_query($value) {
    $value = (int)$value;
    if ($value <= 0) return null;
    return ":$value";
  }
}
