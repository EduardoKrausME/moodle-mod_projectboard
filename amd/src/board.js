/**
 * Accessible Project Board interaction layer. All HTML originates from Moodle Mustache templates.
 *
 * @module mod_projectboard/board
 * @copyright 2026 Eduardo Kraus
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(["jquery", "core/ajax", "core/notification"], function ($, Ajax, Notification) {
    "use strict";

    const getBoard = (app) => app.querySelector("[data-boardid]");
    const getCard = (element) => element.closest("[data-taskid][data-version]");
    const getDialog = (app) => app.querySelector("#projectboard-dialog");
    const message = (app, text, error = false) => {
        const region = app.querySelector("#projectboard-message");
        region.textContent = text;
        region.classList.toggle("projectboard-error", error);
    };
    const findTask = (app, id) => app.querySelector('[data-taskid="' + Number(id) + '"][data-version]');
    const currentBoard = (app) => {
        const board = getBoard(app);
        return board ? Number(board.dataset.boardid) : 0;
    };
    const openDialog = (dialog) => {
        if (dialog && !dialog.open) {
            dialog.showModal();
        }
    };
    const closeDialog = (dialog) => {
        if (dialog && dialog.open) {
            dialog.close();
        }
    };
    const filterCards = (app) => {
        const query = app.querySelector("[data-filter-search]").value.trim().toLocaleLowerCase();
        const priority = app.querySelector("[data-filter-priority]").value;
        app.querySelectorAll(".projectboard-card").forEach((card) => {
            const match = (!query || card.textContent.toLocaleLowerCase().includes(query)) &&
                (!priority || card.dataset.priority === priority);
            card.hidden = !match;
        });
    };
    const request = async (app, action, data = {}, keepDialog = true) => {
        app.setAttribute("aria-busy", "true");
        message(app, "");
        try {
            const result = await Ajax.call([{
                methodname: "mod_projectboard_operation",
                args: {cmid: Number(app.dataset.cmid), action: action, payload: JSON.stringify(data)}
            }])[0];
            if (action === "task_details") {
                app.querySelector("#projectboard-dialog-content").innerHTML = result.dialog;
                const priority = app.querySelector("[data-current-priority]");
                if (priority) {
                    priority.value = priority.dataset.currentPriority;
                }
                openDialog(getDialog(app));
            } else {
                const container = app.querySelector("#projectboard-content");
                container.innerHTML = result.html;
                filterCards(app);
                if (keepDialog && result.dialog && getDialog(app).open) {
                    app.querySelector("#projectboard-dialog-content").innerHTML = result.dialog;
                    const priority = app.querySelector("[data-current-priority]");
                    if (priority) {
                        priority.value = priority.dataset.currentPriority;
                    }
                }
            }
            return result;
        } catch (error) {
            message(app, error.message || "Project Board request failed", true);
            Notification.exception(error);
            throw error;
        } finally {
            app.removeAttribute("aria-busy");
        }
    };
    const collectForm = (form) => {
        const payload = {};
        new FormData(form).forEach((value, key) => {
            payload[key] = value;
        });
        ["startdate", "duedate"].forEach((field) => {
            if (Object.prototype.hasOwnProperty.call(payload, field)) {
                payload[field] = payload[field] ? Math.floor(new Date(payload[field]).getTime() / 1000) : 0;
            }
        });
        if (form.dataset.form === "task_assign") {
            payload.userids = String(payload.userids || "").split(",")
                .map((value) => Number(value.trim())).filter((number) => Number.isInteger(number) && number > 0);
        }
        if (form.dataset.form === "task_create" || form.dataset.form === "grade_save") {
            payload.boardid = Number(payload.boardid) || currentBoard(form.closest("[data-projectboard-app]"));
        }
        return payload;
    };
    const updateVersion = (app, taskid) => {
        const card = findTask(app, taskid);
        return card ? Number(card.dataset.version) : null;
    };
    const moveCard = (app, card, columnid, position = 0) => {
        if (!card) {
            return Promise.resolve();
        }
        return request(app, "task_move", {
            taskid: Number(card.dataset.taskid),
            columnid: Number(columnid),
            position: position,
            version: Number(card.dataset.version)
        });
    };

    const init = (cmid) => {
        const app = document.querySelector('[data-projectboard-app][data-cmid="' + Number(cmid) + '"]');
        if (!app) {
            return;
        }
        let dragging = null;
        app.addEventListener("submit", async (event) => {
            const form = event.target.closest("form[data-form]");
            if (!form) {
                return;
            }
            event.preventDefault();
            const action = form.dataset.form;
            const payload = collectForm(form);
            try {
                const result = await request(app, action, payload);
                if (action === "task_create" || action === "grade_save" || action === "column_create") {
                    closeDialog(form.closest("dialog"));
                }
                if (action === "task_create" && result.taskid) {
                    await request(app, "task_details", {taskid: result.taskid});
                }
                if (action === "comment_add" || action === "check_add" || action === "dep_add") {
                    form.reset();
                }
            } catch (error) {
                // Server-side capability, version and validation failures are surfaced by request().
            }
        });
        app.addEventListener("click", async (event) => {
            const target = event.target.closest("button, a");
            if (!target) {
                return;
            }
            if (target.matches("[data-close-dialog]")) {
                closeDialog(target.closest("dialog"));
                return;
            }
            if (target.matches("[data-open-create], [data-add-in-column]")) {
                const form = app.querySelector("#projectboard-create-dialog form");
                form.reset();
                form.querySelector("[data-current-board]").value = currentBoard(app);
                if (target.dataset.addInColumn) {
                    form.querySelector("[name=columnid]").value = target.dataset.addInColumn;
                }
                openDialog(app.querySelector("#projectboard-create-dialog"));
                return;
            }
            if (target.matches("[data-open-column]")) {
                openDialog(app.querySelector("#projectboard-column-dialog"));
                return;
            }
            if (target.matches("[data-open-grade]")) {
                app.querySelector("#projectboard-grade-dialog [data-current-board]").value = currentBoard(app);
                openDialog(app.querySelector("#projectboard-grade-dialog"));
                return;
            }
            if (target.dataset.openTask) {
                try {
                    await request(app, "task_details", {taskid: Number(target.dataset.openTask)});
                } catch (error) {
                    // The request helper exposes errors.
                }
                return;
            }
            if (target.dataset.moveTask) {
                const card = getCard(target);
                const to = card.querySelector("[data-move-target]");
                await moveCard(app, card, to.value).catch(() => {});
                return;
            }
            if (target.dataset.columnOrder) {
                await request(app, "column_order", {
                    boardid: currentBoard(app),
                    columnid: Number(target.dataset.columnid),
                    direction: Number(target.dataset.columnOrder)
                }).catch(() => {});
                return;
            }
            if (target.dataset.columnArchive) {
                if (window.confirm(target.getAttribute("aria-label"))) {
                    await request(app, "column_archive", {
                        boardid: currentBoard(app), columnid: Number(target.dataset.columnArchive)
                    }).catch(() => {});
                }
                return;
            }
            if (target.dataset.taskDuplicate) {
                await request(app, "task_duplicate", {
                    taskid: Number(target.dataset.taskDuplicate)
                }).catch(() => {});
                return;
            }
            if (target.dataset.taskArchive) {
                if (window.confirm(target.textContent.trim())) {
                    const taskid = Number(target.dataset.taskArchive);
                    const detail = app.querySelector("[data-details-task]");
                    await request(app, "task_archive", {
                        taskid: taskid, version: Number(detail.dataset.version)
                    }).then(() => closeDialog(getDialog(app))).catch(() => {});
                }
                return;
            }
            if (target.dataset.taskSubtask) {
                const details = app.querySelector("[data-details-task]");
                const form = app.querySelector("#projectboard-create-dialog form");
                form.reset();
                form.querySelector("[data-current-board]").value = Number(details.dataset.boardid);
                const hidden = form.querySelector("[name=parentid]") || document.createElement("input");
                hidden.type = "hidden";
                hidden.name = "parentid";
                hidden.value = target.dataset.taskSubtask;
                form.appendChild(hidden);
                openDialog(app.querySelector("#projectboard-create-dialog"));
                return;
            }
            if (target.dataset.checkDelete) {
                await request(app, "check_delete", {
                    taskid: Number(target.dataset.taskid),
                    itemid: Number(target.dataset.checkDelete)
                }).catch(() => {});
                return;
            }
            if (target.dataset.depDelete) {
                const detail = app.querySelector("[data-details-task]");
                await request(app, "dep_delete", {
                    taskid: Number(detail.dataset.detailsTask),
                    dependsid: Number(target.dataset.depDelete)
                }).catch(() => {});
                return;
            }
            if (target.dataset.replyTo) {
                const input = app.querySelector("[data-reply-id]");
                input.value = Number(target.dataset.replyTo);
                input.closest("form").querySelector("textarea").focus();
            }
        });
        app.addEventListener("change", (event) => {
            if (event.target.matches("[data-board-select]")) {
                const boardid = Number(event.target.value);
                app.querySelectorAll("[data-current-board]").forEach((element) => {
                    element.value = boardid;
                });
                request(app, "refresh", {boardid: boardid}).catch(() => {});
            } else if (event.target.matches("[data-check-toggle]")) {
                const target = event.target;
                request(app, "check_toggle", {
                    taskid: Number(target.dataset.taskid), itemid: Number(target.dataset.checkToggle)
                }).catch(() => { target.checked = !target.checked; });
            }
        });
        app.querySelector("[data-filter-search]").addEventListener("input", () => filterCards(app));
        app.querySelector("[data-filter-priority]").addEventListener("change", () => filterCards(app));
        app.addEventListener("dragstart", (event) => {
            const card = getCard(event.target);
            if (card && card.getAttribute("draggable") === "true") {
                dragging = card;
                event.dataTransfer.effectAllowed = "move";
                event.dataTransfer.setData("text/plain", card.dataset.taskid);
            }
        });
        app.addEventListener("dragover", (event) => {
            const zone = event.target.closest("[data-dropzone]");
            if (zone && dragging) {
                event.preventDefault();
                event.dataTransfer.dropEffect = "move";
                zone.classList.add("projectboard-dragover");
            }
        });
        app.addEventListener("dragleave", (event) => {
            const zone = event.target.closest("[data-dropzone]");
            if (zone && !zone.contains(event.relatedTarget)) {
                zone.classList.remove("projectboard-dragover");
            }
        });
        app.addEventListener("drop", (event) => {
            const zone = event.target.closest("[data-dropzone]");
            if (!zone || !dragging) {
                return;
            }
            event.preventDefault();
            const cards = Array.from(zone.querySelectorAll(".projectboard-card"));
            const hovered = event.target.closest(".projectboard-card");
            const position = hovered ? cards.indexOf(hovered) : cards.length;
            const card = dragging;
            dragging = null;
            zone.classList.remove("projectboard-dragover");
            moveCard(app, card, zone.dataset.dropzone, position).catch(() => {});
        });
        app.addEventListener("dragend", () => {
            dragging = null;
            app.querySelectorAll(".projectboard-dragover").forEach((element) => {
                element.classList.remove("projectboard-dragover");
            });
        });
        app.addEventListener("keydown", (event) => {
            const card = getCard(event.target);
            if (!card || event.target !== card || event.altKey || event.ctrlKey || event.metaKey) {
                return;
            }
            if (event.key === "Enter" || event.key === " ") {
                event.preventDefault();
                request(app, "task_details", {taskid: Number(card.dataset.taskid)}).catch(() => {});
            }
            if (event.shiftKey && (event.key === "ArrowRight" || event.key === "ArrowLeft")) {
                const columns = Array.from(app.querySelectorAll("[data-columnid]"));
                const source = columns.indexOf(card.closest("[data-columnid]"));
                const next = source + (event.key === "ArrowRight" ? 1 : -1);
                if (columns[next]) {
                    event.preventDefault();
                    moveCard(app, card, columns[next].dataset.columnid).catch(() => {});
                }
            }
        });
    };
    return {init: init};
});
