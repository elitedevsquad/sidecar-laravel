import { defineConfig } from "vite";

export default defineConfig({
    build: {
        lib: {
            entry: "resources/js/index.js",
            name: "SidecarLaravel",
            fileName: () => "sidecar.js",
            formats: ["iife"],
        },
        outDir: "dist",
        emptyOutDir: true,
        minify: true,
        rollupOptions: {
            output: {
                inlineDynamicImports: true,
            },
        },
    },
});
