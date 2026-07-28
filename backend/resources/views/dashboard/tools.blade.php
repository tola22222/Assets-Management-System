<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tools Inventory Dashboard</title>
    <link rel="icon" type="image/png" href="{{ asset('images/Favicon1080x1080.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/Favicon1080x1080.png') }}">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brand: '#128a43',
                        'brand-dark': '#0e6e35',
                        'brand-light': '#e6f7ed',
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-gray-100 dark:bg-gray-900 p-8 flex justify-center items-start min-h-screen">

    <div class="w-full max-w-6xl bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 shadow-sm p-6 space-y-4 font-sans text-gray-700 dark:text-gray-300 antialiased">

        <div class="flex items-center justify-between gap-4">
            <div class="relative w-80">
                <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 pointer-events-none text-gray-400 dark:text-gray-500">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                    </svg>
                </span>
                <input type="text" placeholder="Search Item" class="w-full bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-xl py-2.5 pl-11 pr-4 text-sm tracking-wide placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:border-brand focus:ring-1 focus:ring-brand transition dark:text-gray-200">
            </div>

            <div class="flex items-center gap-3">
                <div class="p-2.5 text-brand bg-brand-light rounded-xl">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                    </svg>
                </div>

                <button class="bg-brand hover:bg-brand-dark text-white font-semibold text-sm px-5 py-2.5 rounded-xl shadow-sm flex items-center gap-2 transition">
                    <svg width="22" height="22" viewBox="0 0 22 22" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M10.75 6.75V14.75M14.75 10.75H6.75M10.75 20.75C16.2728 20.75 20.75 16.2728 20.75 10.75C20.75 5.22715 16.2728 0.75 10.75 0.75C5.22715 0.75 0.75 5.22715 0.75 10.75C0.75 16.2728 5.22715 20.75 10.75 20.75Z" stroke="white" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    Add Tool
                </button>

                <button class="border border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-400 font-semibold text-sm px-5 py-2.5 rounded-xl hover:bg-gray-50 dark:hover:bg-gray-800 flex items-center gap-2 transition">
                    <svg class="w-5 h-5 text-gray-500 dark:text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"></path>
                    </svg>
                    Filter
                </button>
            </div>
        </div>

        <div class="overflow-x-auto border border-gray-100 dark:border-gray-700 rounded-xl">
            <table class="w-full text-left border-collapse text-sm">
                <thead>
                    <tr class="text-gray-400 dark:text-gray-500 font-medium bg-gray-50/70 dark:bg-gray-800/70 border-b border-gray-100 dark:border-gray-700">
                        <th class="p-4 pl-5 w-10">
                            <input type="checkbox" class="rounded border-gray-300 dark:border-gray-600 text-brand focus:ring-brand w-4 h-4 dark:bg-gray-700">
                        </th>
                        <th class="p-4 font-semibold tracking-wide">
                            <div class="flex items-center gap-1.5 cursor-pointer select-none hover:text-gray-600 dark:hover:text-gray-300">
                                Tools name
                                <svg class="w-3 h-3 text-gray-400 dark:text-gray-500" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M12 3l-5 6h10l-5-6zm0 18l5-6H7l5 6z"/>
                                </svg>
                            </div>
                        </th>
                        <th class="p-4 font-semibold tracking-wide">Image</th>
                        <th class="p-4 font-semibold tracking-wide">Model</th>
                        <th class="p-4 font-semibold tracking-wide">Type</th>
                        <th class="p-4 font-semibold tracking-wide">Store</th>
                        <th class="p-4 font-semibold tracking-wide">Amount</th>
                        <th class="p-4 font-semibold tracking-wide">Project</th>
                        <th class="p-4 pr-5 font-semibold tracking-wide">Account</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700 font-normal text-gray-600 dark:text-gray-400">
                    <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-800/50 transition">
                        <td class="p-4 pl-5">
                            <input type="checkbox" class="rounded border-gray-300 dark:border-gray-600 text-brand focus:ring-brand w-4 h-4 dark:bg-gray-700">
                        </td>
                        <td class="p-4 font-medium text-gray-900 dark:text-white">Gas Kitting</td>
                        <td class="p-4">
                            <div class="w-12 h-10 bg-gray-100 dark:bg-gray-900 rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700">
                                <img src="https://images.unsplash.com/photo-1517336714731-489689fd1ca8?auto=format&fit=crop&w=120&q=80" alt="Tool" class="w-full h-full object-cover">
                            </div>
                        </td>
                        <td class="p-4 text-gray-500 dark:text-gray-400 font-mono text-xs">G-7893</td>
                        <td class="p-4">IE Project Items</td>
                        <td class="p-4 text-gray-500 dark:text-gray-400">22 House Store</td>
                        <td class="p-4 font-medium">1 pcs</td>
                        <td class="p-4">HQ</td>
                        <td class="p-4 pr-5">
                            <span class="text-emerald-600 bg-emerald-50 px-2.5 py-1 rounded-full text-xs font-semibold">Activated</span>
                        </td>
                    </tr>
                    <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-800/50 transition">
                        <td class="p-4 pl-5">
                            <input type="checkbox" class="rounded border-gray-300 dark:border-gray-600 text-brand focus:ring-brand w-4 h-4 dark:bg-gray-700">
                        </td>
                        <td class="p-4 font-medium text-gray-900 dark:text-white">Condet</td>
                        <td class="p-4">
                            <div class="w-12 h-10 bg-gray-100 dark:bg-gray-900 rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700">
                                <img src="https://images.unsplash.com/photo-1517336714731-489689fd1ca8?auto=format&fit=crop&w=120&q=80" alt="Tool" class="w-full h-full object-cover">
                            </div>
                        </td>
                        <td class="p-4 text-gray-500 dark:text-gray-400 font-mono text-xs">Co-7898</td>
                        <td class="p-4">IE Project Items</td>
                        <td class="p-4 text-gray-500 dark:text-gray-400">HQ Main Store</td>
                        <td class="p-4 font-medium">3 pcs</td>
                        <td class="p-4">HQ</td>
                        <td class="p-4 pr-5">
                            <span class="text-amber-600 bg-amber-50 dark:bg-amber-900/30 px-2.5 py-1 rounded-full text-xs font-semibold">Need Invitation</span>
                        </td>
                    </tr>
                    <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-800/50 transition">
                        <td class="p-4 pl-5">
                            <input type="checkbox" class="rounded border-gray-300 dark:border-gray-600 text-brand focus:ring-brand w-4 h-4 dark:bg-gray-700">
                        </td>
                        <td class="p-4 font-medium text-gray-900 dark:text-white">Condet</td>
                        <td class="p-4">
                            <div class="w-12 h-10 bg-gray-100 dark:bg-gray-900 rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700">
                                <img src="https://images.unsplash.com/photo-1517336714731-489689fd1ca8?auto=format&fit=crop&w=120&q=80" alt="Tool" class="w-full h-full object-cover">
                            </div>
                        </td>
                        <td class="p-4 text-gray-500 dark:text-gray-400 font-mono text-xs">G-7893</td>
                        <td class="p-4">IE Project Items</td>
                        <td class="p-4 text-gray-500 dark:text-gray-400">HQ Main Store</td>
                        <td class="p-4 font-medium">5 pcs</td>
                        <td class="p-4">HQ</td>
                        <td class="p-4 pr-5">
                            <span class="text-emerald-600 bg-emerald-50 dark:bg-emerald-900/30 px-2.5 py-1 rounded-full text-xs font-semibold">Activated</span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="pt-2 flex items-center justify-between text-sm text-gray-500 dark:text-gray-400">
            <div class="flex items-center gap-2">
                <span>Showing</span>
                <div class="relative">
                    <select class="appearance-none bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-xl pl-3 pr-8 py-2 text-gray-700 dark:text-gray-200 font-medium focus:outline-none focus:border-brand transition cursor-pointer">
                        <option value="10">10</option>
                        <option value="25">25</option>
                        <option value="50">50</option>
                    </select>
                    <span class="absolute inset-y-0 right-0 flex items-center pr-2.5 pointer-events-none text-gray-400 dark:text-gray-500">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </span>
                </div>
            </div>

            <div>
                <span>Showing 1 to 10 out of 40 records</span>
            </div>

            <div class="flex items-center gap-1">
                <button class="p-2 text-gray-400 dark:text-gray-500 hover:text-gray-600 dark:hover:text-gray-300 transition rounded-lg hover:bg-gray-50 dark:hover:bg-gray-800">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5"></path>
                    </svg>
                </button>

                <button class="w-8 h-8 rounded-lg flex items-center justify-center font-semibold text-sm bg-brand-light text-brand dark:bg-brand/20 border border-brand/20">1</button>
                <button class="w-8 h-8 rounded-lg flex items-center justify-center font-medium text-sm text-gray-500 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800 transition">2</button>
                <button class="w-8 h-8 rounded-lg flex items-center justify-center font-medium text-sm text-gray-500 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800 transition">3</button>
                <button class="w-8 h-8 rounded-lg flex items-center justify-center font-medium text-sm text-gray-500 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800 transition">4</button>

                <button class="p-2 text-gray-400 dark:text-gray-500 hover:text-gray-600 dark:hover:text-gray-300 transition rounded-lg hover:bg-gray-50 dark:hover:bg-gray-800">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5"></path>
                    </svg>
                </button>
            </div>
        </div>

    </div>

</body>
</html>
