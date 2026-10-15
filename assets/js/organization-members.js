document.addEventListener("DOMContentLoaded", function () {
    const page = document.querySelector(".members-main");
    if (!page) return;

    const csrfToken = page.dataset.csrfToken || "";
    const memberList = document.getElementById("memberList");
    const memberActionStatus = document.getElementById("memberActionStatus");
    const invitationList = document.getElementById("invitationList");
    const memberCount = document.getElementById("memberCount");
    const status = document.getElementById("inviteStatus");
    const invitationStatus = document.getElementById("invitationActionStatus");
    const form = document.getElementById("organizationInviteForm");

    function createText(tagName, className, text) {
        const element = document.createElement(tagName);
        if (className) element.className = className;
        element.textContent = text;
        return element;
    }

    function renderMembers(members) {
        memberCount.textContent = String(members.length);
        memberList.replaceChildren();
        if (!members.length) {
            memberList.append(createText("p", "access-empty", "No workspace members found."));
            return;
        }
        members.forEach(function (member) {
            const row = createText("article", "access-list-row", "");
            const identity = createText("div", "access-member-identity", "");
            const avatar = createText("span", "access-avatar", (member.name || "U").trim().slice(0, 1).toUpperCase());
            const detail = createText("span", "access-member-copy", "");
            detail.append(createText("strong", "", member.name || "Member"));
            detail.append(createText("small", "", member.email || ""));
            identity.append(avatar, detail);
            const actions = createText("div", "access-list-actions", "");
            const roleLabel = member.role === "admin" ? "Main Admin"
                : (member.role === "support" ? "Support Member" : "Customer");
            const role = createText("span", "access-role access-role-" + member.role, roleLabel);
            const resetCode = createText("button", "access-resend", "Reset code");
            resetCode.type = "button";
            resetCode.dataset.userId = String(member.id);
            resetCode.setAttribute("aria-label", "Reset sign-in code for " + (member.name || "member"));
            actions.append(role, resetCode);
            row.append(identity, actions);
            memberList.append(row);
        });
    }

    function renderInvitations(invitations) {
        invitationList.replaceChildren();
        if (!invitations.length) {
            invitationList.append(createText("p", "access-empty", "No pending invitations."));
            return;
        }
        invitations.forEach(function (invitation) {
            const row = createText("article", "access-list-row", "");
            const identity = createText("div", "access-member-identity", "");
            const icon = createText("span", "access-avatar access-avatar-pending", "✉");
            const detail = createText("span", "access-member-copy", "");
            detail.append(createText("strong", "", invitation.email || ""));
            detail.append(createText("small", "", "Expires " + new Date(invitation.expires_at.replace(" ", "T")).toLocaleString()));
            identity.append(icon, detail);
            const actions = createText("div", "access-list-actions", "");
            const roleLabel = invitation.role === "admin" ? "Main Admin"
                : (invitation.role === "support" ? "Support Member" : "Customer");
            actions.append(createText("span", "access-role", roleLabel));
            const resend = createText("button", "access-resend", "Resend");
            resend.type = "button";
            resend.dataset.invitationAction = "resend";
            resend.dataset.invitationId = String(invitation.id);
            resend.setAttribute("aria-label", "Resend invitation to " + (invitation.email || "recipient"));
            const revoke = createText("button", "access-revoke", "Revoke");
            revoke.type = "button";
            revoke.dataset.invitationAction = "revoke";
            revoke.dataset.invitationId = String(invitation.id);
            revoke.setAttribute("aria-label", "Revoke invitation to " + (invitation.email || "recipient"));
            actions.append(resend, revoke);
            row.append(identity, actions);
            invitationList.append(row);
        });
    }

    async function loadAccess() {
        try {
            const response = await fetch("api/organization_invitations.php", {
                headers: { "Accept": "application/json" },
                cache: "no-store"
            });
            const data = await response.json();
            if (!response.ok || !data.success) throw new Error(data.message || "Unable to load workspace access.");
            renderMembers(Array.isArray(data.members) ? data.members : []);
            renderInvitations(Array.isArray(data.invitations) ? data.invitations : []);
        } catch (error) {
            memberList.replaceChildren(createText("p", "access-message is-error", error.message));
            invitationList.replaceChildren(createText("p", "access-message is-error", error.message));
        }
    }

    form.addEventListener("submit", async function (event) {
        event.preventDefault();
        status.className = "access-status";
        status.textContent = "Sending invitation…";
        const button = form.querySelector("button[type='submit']");
        button.disabled = true;
        try {
            const body = new FormData(form);
            body.set("action", "invite");
            body.set("csrf_token", csrfToken);
            const response = await fetch("api/organization_invitations.php", {
                method: "POST",
                body: body,
                headers: { "Accept": "application/json" },
                cache: "no-store"
            });
            const data = await response.json();
            if (!response.ok || !data.success) throw new Error(data.message || "Invitation could not be sent.");
            status.className = "access-status is-success";
            status.textContent = data.message;
            form.reset();
            await loadAccess();
        } catch (error) {
            status.className = "access-status is-error";
            status.textContent = error.message;
        } finally {
            button.disabled = false;
        }
    });

    invitationList.addEventListener("click", async function (event) {
        const button = event.target.closest("button[data-invitation-action][data-invitation-id]");
        if (!button) return;
        const action = button.dataset.invitationAction;
        if (action === "revoke" && !window.confirm("Revoke this invitation? The recipient will no longer be able to use its code.")) return;
        const rowButtons = button.closest(".access-list-actions").querySelectorAll("button");
        rowButtons.forEach(function (rowButton) { rowButton.disabled = true; });
        invitationStatus.className = "access-status";
        invitationStatus.textContent = action === "resend" ? "Resending invitation…" : "Revoking invitation…";
        const body = new FormData();
        body.set("action", action);
        body.set("invitation_id", button.dataset.invitationId);
        body.set("csrf_token", csrfToken);
        try {
            const response = await fetch("api/organization_invitations.php", {
                method: "POST",
                body: body,
                headers: { "Accept": "application/json" },
                cache: "no-store"
            });
            const data = await response.json();
            if (!response.ok || !data.success) {
                const failure = action === "resend" ? "resent" : "revoked";
                throw new Error(data.message || "Invitation could not be " + failure + ".");
            }
            invitationStatus.className = "access-status is-success";
            invitationStatus.textContent = data.message;
            await loadAccess();
        } catch (error) {
            invitationStatus.className = "access-status is-error";
            invitationStatus.textContent = error.message;
            rowButtons.forEach(function (rowButton) { rowButton.disabled = false; });
        }
    });

    memberList.addEventListener("click", async function (event) {
        const button = event.target.closest("button[data-user-id]");
        if (!button) return;
        if (!window.confirm("Reset this member's sign-in code and email a new one? Their current code will stop working.")) return;
        button.disabled = true;
        memberActionStatus.className = "access-status";
        memberActionStatus.textContent = "Sending a new sign-in code…";
        const body = new FormData();
        body.set("action", "reset_access_code");
        body.set("user_id", button.dataset.userId);
        body.set("csrf_token", csrfToken);
        try {
            const response = await fetch("api/organization_invitations.php", {
                method: "POST",
                body: body,
                headers: { "Accept": "application/json" },
                cache: "no-store"
            });
            const data = await response.json();
            if (!response.ok || !data.success) {
                throw new Error(data.message || "The sign-in code could not be reset.");
            }
            memberActionStatus.className = "access-status is-success";
            memberActionStatus.textContent = data.message;
        } catch (error) {
            memberActionStatus.className = "access-status is-error";
            memberActionStatus.textContent = error.message;
        } finally {
            button.disabled = false;
        }
    });

    loadAccess();
});
