/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */
define(["require","exports","jquery","TYPO3/CMS/Backend/jquery.clearable"],function(e,s,r){"use strict";return new function(){var e=this;this.$searchFields=r('input[name="search_field"]'),this.searchResultShown=""!==this.$searchFields.first().val(),this.$searchFields.clearable({onClear:function(s){e.searchResultShown&&r(s.currentTarget).closest("form").submit()}}),self.location.hash&&window.scrollTo(window.pageXOffset,window.pageYOffset-40)}});