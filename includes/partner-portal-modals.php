<?php
/**
 * Profile + deployment modals (shared by partner portal home and Agency & contracts page).
 */
?>
<div id="ppProfileModal" class="modal-wrap partner-portal-modal" aria-hidden="true">
    <div class="modal-card glass-card partner-portal-modal-card" role="dialog" aria-modal="true" aria-labelledby="ppProfileModalTitle">
        <div class="partner-portal-modal-head">
            <h3 id="ppProfileModalTitle" class="partner-portal-modal-title">Profile</h3>
            <button type="button" class="icon-btn" id="ppProfileModalClose" aria-label="Close">×</button>
        </div>
        <p id="ppProfileModalLead" class="partner-portal-modal-lead"></p>
        <div id="ppProfileViewPanel" class="partner-portal-modal-view"></div>
        <form id="ppProfileEditForm" class="partner-portal-edit-form" hidden>
            <label class="partner-portal-label">Contact person</label>
            <input type="text" name="contact_person" id="ppEditContactPerson" class="partner-portal-input" maxlength="255" autocomplete="name">
            <label class="partner-portal-label">Email</label>
            <input type="email" name="email" id="ppEditEmail" class="partner-portal-input" maxlength="255" autocomplete="email">
            <label class="partner-portal-label">Phone 1</label>
            <input type="text" name="phone" id="ppEditPhone" class="partner-portal-input" maxlength="80" autocomplete="tel">
            <label class="partner-portal-label">Phone 2</label>
            <input type="text" name="phone2" id="ppEditPhone2" class="partner-portal-input" maxlength="80">
            <label class="partner-portal-label">Fax</label>
            <input type="text" name="fax" id="ppEditFax" class="partner-portal-input" maxlength="80">
            <label class="partner-portal-label">Mobile</label>
            <input type="text" name="mobile" id="ppEditMobile" class="partner-portal-input" maxlength="80" autocomplete="tel">
            <label class="partner-portal-label">Address (English)</label>
            <textarea name="address_en" id="ppEditAddressEn" class="partner-portal-input partner-portal-textarea" rows="3" maxlength="2000"></textarea>
            <label class="partner-portal-label">Address (Arabic)</label>
            <textarea name="address_ar" id="ppEditAddressAr" class="partner-portal-input partner-portal-textarea" rows="2" maxlength="2000"></textarea>
            <p id="ppProfileFormMsg" class="partner-portal-modal-msg" hidden></p>
            <div class="partner-portal-modal-footer">
                <button type="button" class="muted-btn" id="ppProfileCancelBtn">Cancel</button>
                <button type="submit" class="neon-btn" id="ppProfileSaveBtn">Save</button>
            </div>
        </form>
        <div id="ppProfileViewFooter" class="partner-portal-modal-footer">
            <button type="button" class="muted-btn" id="ppProfileCloseBtn">Close</button>
        </div>
    </div>
</div>

<div id="ppContractModal" class="modal-wrap partner-portal-modal" aria-hidden="true">
    <div class="modal-card glass-card partner-portal-modal-card partner-portal-modal-card--compact" role="dialog" aria-modal="true" aria-labelledby="ppContractModalTitle">
        <div class="partner-portal-modal-head">
            <h3 id="ppContractModalTitle" class="partner-portal-modal-title">Deployment</h3>
            <button type="button" class="icon-btn" id="ppContractModalClose" aria-label="Close">×</button>
        </div>
        <dl class="agency-detail-dl partner-portal-contract-dl" id="ppContractModalBody"></dl>
        <div class="partner-portal-modal-footer">
            <button type="button" class="muted-btn" id="ppContractCloseBtn">Close</button>
        </div>
    </div>
</div>
