# MarkBase Wiki Guide

Welcome to MarkBase. This wiki stores Markdown files on disk and lets you browse, edit, and organize them from the web UI.

## Basic Navigation

- Use the sidebar to browse folders and pages.
- Click a page name to open it.
- Folder routes open that folder's `README.md` when present; otherwise you get a folder listing.
- Use breadcrumbs at the top to move back up the current path.

## Creating and Editing Pages

- Click ![Edit](actions/edit.svg) on a page to modify Markdown content.
- Click ![New](actions/new.svg) in the breadcrumb action row to create a new page.
- Enter a folder path and page name in the modal, then save. If you enter a path that does not exist, it will be created.
- If you click ![Edit](actions/edit.svg) from a folder listing page, MarkBase creates that folder's `README.md` and opens it.

## Uploading Markdown Files

- Click ![Upload](actions/upload.svg) in the breadcrumb action row.
- The `Path` field is prefilled with the current page/folder path and can be changed before upload.
- Drop one or more `.md` files into the drag-drop area, or click `Choose Files` to open the file picker.
- Files are uploaded as wiki pages in the selected path.
- The upload dialog does not close automatically after upload; use ![Close](actions/close.svg) when done.

## Linking Pages

Use standard Markdown links:

```md
[Install Notes](../Ops/Install.md)
```

Use wiki links:

```md
[[Path/To/Page]]
[[PageName]]
```

- `[[Path/To/Page]]` links directly to that page.
- `[[PageName]]` links by filename and may open a disambiguation page if multiple matches exist.

## Search and Backlinks

- Use the search bar to find content across pages.
- Search results include snippets with highlighted matches.
- Open a page to view backlinks (pages that reference it).

## Organizing Content

- Use ![Move / Rename](actions/move-rename.svg) to rename a page or move it to another folder.
- Moving a folder moves the full subtree.
- Use ![Delete](actions/delete.svg) carefully; deleting a folder removes all pages below it.

## Images and Assets

- Put content images in `img/` and reference them from Markdown.
- Internal UI icons/logo are served from `img-internal/`.

## Tips

- Keep folder names consistent to reduce ambiguous `[[PageName]]` links.
- You can navigate to folders by selecting a file in that folder and using the breadcrumbs. This will let you delete or move the whole folder.
- Use a `README.md` in each folder for a useful landing page. Directly using ![Edit](actions/edit.svg) on a folder will automatically create the README.md ready for you to edit.
- Re-run indexing with `php bin/reindex.php` if you perform large external file edits.
