<?php
/*
Name: Admin Bookmarks
Author: SeanChDavis
Description: Select admin pages to bookmark for quick access via dropdown menu.
Version: 0.2
Requires: 0.1
Class: SCHD_Admin_Bookmarks
Type: Admin
Docs:
License: MIT
License URI: https://opensource.org/licenses/mit
Copyright (c) 2025 SeanChDavis
*/

class SCHD_Admin_Bookmarks extends PM_Plugin {
    public $title = 'Admin Bookmarks';
    public $type = 'box';

    // Change this number to increase or decrease the number of bookmarks
    private $max_bookmarks = 10;

    /**
     * Returns the site options for Admin Bookmarks.
     *
     * @return array The site options array with fields for the set number of bookmarks.
     */
    public function site_options(): array
    {
        global $motor;
        $rows = $motor->db->get_rows("SELECT id, title, slug, parent FROM pm_admin_content WHERE status = 'live' ORDER BY title");

        // Build a map of pages with their IDs as keys
        $pages = [];
        foreach ($rows as $row) {
            $pages[(int) $row['id']] = $row;
        }

        // Build the path for each page
        $build_path = function($id) use (&$pages, &$build_path) {
            $segments = [];
            while ($id && isset($pages[$id])) {
                $slug = trim($pages[$id]['slug']);
                if ($slug !== '') {
                    array_unshift($segments, $slug);
                }
                $id = (int) $pages[$id]['parent'];
            }
            return implode('/', $segments);
        };

        // Build the label for each page
        $build_label = function($id) use (&$pages) {
            $titles = [];
            while ($id && isset($pages[$id])) {
                $titles[] = $pages[$id]['title'];
                $id = (int) $pages[$id]['parent'];
            }
            return implode(' > ', array_reverse($titles));
        };

        // Build the options for the select fields
        $slug_options = ['' => '-- Select a Page --'];
        foreach ($pages as $id => $page) {
            if (!empty($page['slug'])) {
                $full_path = $build_path($id);
                $slug_options[$full_path] = $build_label($id);
            }
        }

        // Create the fields for the site options
        $fields = [
            'bookmark_desc' => array(
                'type' => 'custom',
                'html' => '<p style="max-width:768px">Select up to pages to ' . $this->max_bookmarks . ' to bookmark for quick access in the admin area.</p>'
            )
        ];
        for ($i = 1; $i <= $this->max_bookmarks; $i++) {
            $fields["bookmark_{$i}_url"] = array(
                'type' => 'select',
                'label' => "Bookmark $i",
                'options' => $slug_options
            );
        }

        return $fields;
    }

    /**
     * Retrieves the bookmarks from the site options and builds the bookmark list.
     *
     * @return array The list of bookmarks with labels and URLs.
     */
    public function get_bookmarks(): array
    {
        global $motor;
        $rows = $motor->db->get_rows("SELECT id, title, slug, parent FROM pm_admin_content WHERE status = 'live' ORDER BY title");

        // Build a map of pages with their IDs as keys
        $pages = [];
        foreach ($rows as $row) {
            $pages[(int) $row['id']] = $row;
        }

        // Build the label and path for each page
        $build_label = function($id) use (&$pages) {
            $titles = [];
            while ($id && isset($pages[$id])) {
                $titles[] = $pages[$id]['title'];
                $id = (int) $pages[$id]['parent'];
            }
            return implode(' > ', array_reverse($titles));
        };

        // Build the path index for quick lookup
        $path_index = [];
        $build_path = function($id) use (&$pages, &$build_path) {
            $segments = [];
            while ($id && isset($pages[$id])) {
                $slug = trim($pages[$id]['slug']);
                if ($slug !== '') {
                    array_unshift($segments, $slug);
                }
                $id = (int) $pages[$id]['parent'];
            }
            return implode('/', $segments);
        };

        // Create the path index for bookmarks
        foreach ($pages as $id => $page) {
            if (!empty($page['slug'])) {
                $path = $build_path($id);
                $path_index[$path] = $build_label($id);
            }
        }

        // Retrieve the bookmarks from site options
        $bookmarks = [];
        for ($i = 1; $i <= $this->max_bookmarks; $i++) {
            $url = trim($this->site_options["bookmark_{$i}_url"] ?? '');
            if ($url && isset($path_index[$url])) {
                $bookmarks[] = ['label' => $path_index[$url], 'url' => $url];
            }
        }

        return $bookmarks;
    }

    /**
     * Outputs the HTML for the admin bookmarks dropdown.
     *
     * @param int $depth The depth of the HTML output.
     */
    public function html($depth = 0): void
    {
        global $motor;
        $bookmarks = $this->get_bookmarks();
        $tab = str_repeat("\t", $depth);

        if (empty($bookmarks)) {
            return;
        }

        $dropdownClass = "admin-bookmarks-dropdown";

        // Output the CSS for the dropdown
        echo "$tab<style>
            .admin-bookmarks-dropdown { position: relative; padding-top: 10px; }
            .admin-bookmarks-toggle { color: unset; cursor: pointer; text-decoration: none; }
            .admin-bookmarks-toggle:hover { text-decoration: underline; }
            .admin-bookmarks-list { display: none; position: absolute; top: 100%; left: 0; background: white; border: 1px solid rgba(0, 0, 0, 0.1); margin: 0; padding: 4px 0; list-style: none; z-index: 9999; border-radius: 7px }
            .admin-bookmarks-item { padding: 4px 17px; }
            .admin-bookmarks-link { text-decoration: none; color: #333; }
            .admin-bookmarks-link:hover { text-decoration: underline; }
            .admin-bookmarks-dropdown.open .admin-bookmarks-list { display: block; }
        </style>\n";

        // Output the dropdown HTML
        echo "$tab<div class=\"$dropdownClass\">\n";
        echo "$tab\t<a class=\"admin-bookmarks-toggle\">Bookmarks</a>\n";
        echo "$tab\t<ul class=\"admin-bookmarks-list\">\n";

        foreach ($bookmarks as $bookmark) {
            $label = isset($bookmark['label']) ? $bookmark['label'] : $bookmark['url'];
            $url = $motor->url('admin/' . $bookmark['url']);
            echo "$tab\t\t<li class='admin-bookmarks-item'><a href='$url' class='admin-bookmarks-link'>$label</a></li>\n";
        }

        echo "$tab\t</ul>\n";
        echo "$tab</div>\n";

        // Simple JS for dropdown toggle
        echo "$tab<script>document.addEventListener('DOMContentLoaded', function() {
            const btn = document.querySelector('.admin-bookmarks-dropdown a');
            const menu = document.querySelector('.admin-bookmarks-dropdown ul');
            if (btn && menu) {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    menu.style.display = (menu.style.display === 'block') ? 'none' : 'block';
                });
                document.addEventListener('click', function(e) {
                    if (!btn.contains(e.target) && !menu.contains(e.target)) {
                        menu.style.display = 'none';
                    }
                });
            }
        });</script>\n";
    }
}
