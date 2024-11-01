jQuery(function ( $ ) {
    const superflowWordpressPlugin = function () {
        const superflowApiBaseUrl = `https://us-central1-snippyly-sdk-prod.cloudfunctions.net`;
        const superflowPortalUrl = `https://app.usesuperflow.com`;
        const utmQuery = `&utm_medium=wordpress-plugin&utm_source=wordpress&utm_campaign=wordpressPlugin`;

        const Config = {
            apiUrls: {
                wpPluginHandler: `${superflowApiBaseUrl}/wpplugindirecthandler`,
            },
            settingPageUrlTemplate: `${superflowPortalUrl}/dashboard/project/edit/{API_KEY}/{PROJECT_ID}?identifier=client`,
            connectUrl: `${superflowPortalUrl}/signup/wordpress-plugin`,
        };

        const getSettingPageUrl = (apiKey, projectId)  => {
            if (apiKey && projectId) {
                return Config.settingPageUrlTemplate
                    .replace("{API_KEY}", apiKey)
                    .replace("{PROJECT_ID}", projectId)
            } else {
                return ""
            }
        }


        const getConnectUrl = (connectionId)  => {
            return `${Config.connectUrl}?wp_connection_id=${connectionId}${utmQuery}`
        }

        const superFlowApiClient = {
            ping: (apiKey, projectId) => {
                return new Promise((resolve, reject) => {
                    jQuery.ajax({
                        url: Config.apiUrls.wpPluginHandler,
                        "data": JSON.stringify({
                            "data": {
                                apiKey,
                                projectId,
                                eventType: 'ping'
                            }
                        }),
                        method: "POST",
                        headers: {
                            "Content-Type": "application/json",
                        },
                        success: (response) => {
                            resolve({
                                ...(response?.result ?? {}),
                                success: true,
                            })
                        },
                        error: (response) => {
                            console.log(response, Config.apiUrls.verifyManualCredentials+" api error");
                            resolve({
                                ...(response?.error ?? {}),
                                success: false,
                            })
                        },
                    })
                });
            },
            createConnection: (siteUrl, adminEmail) => {
                return new Promise((resolve, reject) => {
                    jQuery.ajax({
                        url: Config.apiUrls.wpPluginHandler,
                        "data": JSON.stringify({
                            "data": {siteUrl, adminEmail, eventType: 'createConnection'}
                        }),
                        method: "POST",
                        headers: {
                            "Content-Type": "application/json",
                        },
                        success: (response) => {
                            resolve({...(response?.result ?? {}), success: true})
                        },
                        error: (response) => {
                            console.log(response, "createConnection api failed");
                            resolve({...(response?.error ?? {}), success: false})
                        },
                    });
                })
            },
            getConnectionStatus: (connectionId, siteUrl, adminEmail) => {
                return new Promise((resolve, reject) => {
                    jQuery.ajax({
                        url: Config.apiUrls.wpPluginHandler,
                        "data": JSON.stringify({
                            "data": {siteUrl, adminEmail, connectionId, eventType: 'getConnectionStatus'}
                        }),
                        method: "POST",
                        headers: {
                            "Content-Type": "application/json",
                        },
                        success: (response) => {
                            resolve({...(response?.result ?? {}), success: true})
                        },
                        error: (response) => {
                            console.log(response, "getConnectionStatus api failed");
                            resolve({...(response?.error ?? {}), success: false})
                        },
                    });
                });
            },
            disconnect: (connectionId, siteUrl, adminEmail) => {
                return new Promise((resolve, reject) => {
                    jQuery.ajax({
                        url: Config.apiUrls.wpPluginHandler,
                        "data": JSON.stringify({
                            "data": {siteUrl, adminEmail, connectionId, eventType: 'disconnect'}
                        }),
                        method: "POST",
                        headers: {
                            "Content-Type": "application/json",
                        },
                        success: (response) => {
                            resolve({...(response?.result ?? {}), success: true})
                        },
                        error: (response) => {
                            console.log(response, "getConnectionStatus api failed");
                            resolve({...(response?.error ?? {}), success: false})
                        },
                    });
                });
            },
        };
        const wpApiClient = {
            saveSettings: async (payload) => {
                return new Promise((resolve, reject) => {
                    var data = {
                        action: 'superflow_save_project_config',
                        security: window.superflowNonce,
                        ...payload,
                    };
                    jQuery.ajax({
                        url: window.superflowAjaxUrl,
                        "data": data,
                        method: "POST",
                        success:  (response) => {
                            try {
                                const latestConfig = JSON.parse(response);
                                resolve({...latestConfig, success: true})
                            } catch(e) {
                                resolve({success: false})
                            }
                        },
                        error:  (response) => {
                            resolve({success: false})
                        },
                    });
                })
            },
            getSettings: async () => {
                return new Promise((resolve, reject) => {
                    var data = {
                        action: 'superflow_get_project_config',
                        security: window.superflowNonce,
                    };
                    jQuery.ajax({
                        url: window.superflowAjaxUrl,
                        "data": data,
                        method: "POST",
                        success:  (response) => {
                            resolve({...JSON.parse(response), success: true})
                        },
                        error:  (response) => {
                            console.log(response, "Error get setting");
                            resolve({...response.responseJSON, success: false})
                        },
                    });
                })
            },
        };

        return {
            wpApiClient,
            getSettingPageUrl,
            superFlowApiClient,
            apiUrls: Config.apiUrls,
            connectUrl: Config.connectUrl,
            getConnectUrl,
        }
    };

    const sfInstance = superflowWordpressPlugin();

    const connectionStart = (connectionId) => {
        window.open(sfInstance.getConnectUrl(connectionId), "_blank");
        return;
    };

    const toggleView = (connected) => {

        const $connectedElements = $('.sf-connected');
        const $nonConnectedElements = $('.sf-not-connected');

        if (connected) {
            $('.sf-not-connected').hide();
            $connectedElements.show();
            $nonConnectedElements.attr('disabled', false);
        } else {
            $nonConnectedElements.show();
            $connectedElements.hide();
        }
    }

    const updateSettings = async (response) => {
        const {
            connection_status,
            superflow_api_key,
            superflow_project_id,
            superflow_connection_id,
            superflow_server_doc_id
        } = window.superflowPluginOptions;

        window.superflowPluginOptions = {
            ...window.superflowPluginOptions,
            superflow_flow_type: "auto",
            superflow_connection_status: response?.state ?? connection_status,
            superflow_api_key: response?.apiKey ?? superflow_api_key,
            superflow_project_id: response?.projectId ?? superflow_project_id,
            superflow_server_doc_id: response?.serverDocId ?? superflow_server_doc_id,
            superflow_connection_id: response?.id ?? superflow_connection_id,
        };

        await sfInstance.wpApiClient.saveSettings(window.superflowPluginOptions);
    }

    const redirectToReview = () => {
        const {
            superflow_api_key,
            superflow_project_id,
            superflow_connection_id,
            superflow_connection_status
        } = window.superflowPluginOptions;

        const siteUrl = window.superflowSiteUrl

        if (superflow_api_key &&
            superflow_project_id &&
            superflow_connection_id &&
            superflow_connection_status && siteUrl) {
            // Disabled redirection to popup blocked error on browser.
            // window.open(`${siteUrl}?review=true&st=token`, '_blank');
        }
    };


    const pullIntervally = (connectionId) => {
        if (!window?.superFlowMaxAttempts) {
            window.superFlowMaxAttempts = 0;
        }
        window.superflowInterval = setInterval(async () => {
            window.superFlowMaxAttempts++;
            const response = await sfInstance.superFlowApiClient.getConnectionStatus(
                connectionId,
                window.superflowSiteUrl,
                window.superflowPluginOptions.superflow_connection_email,
             );

             const {superflow_connection_status, superflow_api_key, superflow_project_id} = window.superflowPluginOptions;

             const canUpdate = superflow_connection_status !== response?.state ||
                 superflow_api_key !== response?.apiKey ||
                 superflow_project_id !== response?.projectId ||
                 connectionId !== response?.id;

             if (response && canUpdate) {
                await updateSettings(response);
             }

             if (response?.state === 'created') {
                toggleView(true);
                clearInterval(window.superflowInterval);
                window.superflowInterval = undefined;
                redirectToReview();
             } else if (window.superFlowMaxAttempts > 20) {
                 toggleView(false);
                 clearInterval(window.superflowInterval);
                 window.superflowInterval = undefined;
                 window.superFlowMaxAttempts = 0;
            }
        }, 10000);
    }


    $(document).on("click", "#manageSuperflowSettingBtn", async (e) => {
        e.preventDefault();

        const {superflow_api_key, superflow_project_id} = window?.superflowPluginOptions;
        if (superflow_api_key && superflow_project_id) {
            const settingPageUrl = sfInstance.getSettingPageUrl(superflow_api_key, superflow_project_id);
            if (settingPageUrl) {
                window.open(settingPageUrl, "_blank");
            }
        }

    });

    $(document).on("click", "#connectToSuperflow", async (e) => {
        e.preventDefault();

        const $connectBtn = $('#connectToSuperflow');

        $connectBtn.attr('disabled', true);

        if (!window?.superflowPluginOptions?.superflow_connection_id) {
            const response = await sfInstance.superFlowApiClient.createConnection(
                window.superflowSiteUrl,
                window.superflowPluginOptions.superflow_connection_email,
             );

             if (response?.state) {
                await updateSettings(response);
             }


            if (response?.state === 'created') {
                toggleView(true);
                $connectBtn.attr('disabled', false);

                redirectToReview();

                return;
             }
        }

        if (window?.superflowPluginOptions?.superflow_connection_id && window?.superflowPluginOptions?.superflow_connection_status !== 'created') {
            connectionStart(window?.superflowPluginOptions?.superflow_connection_id ?? '');
            pullIntervally(window?.superflowPluginOptions?.superflow_connection_id);
        }
    });


    $(document).on("click", "#disconnectToSuperflow", async (e) => {
        e.preventDefault();

        const $disconnectBtn = $('#disconnectToSuperflow');
        $disconnectBtn.attr("disabled", true);

        if (window.superflowPluginOptions?.superflow_connection_id && window?.superflowSiteUrl && window.superflowPluginOptions?.superflow_connection_email) {
            try {
                await sfInstance.superFlowApiClient.disconnect(
                    window.superflowPluginOptions?.superflow_connection_id,
                    window.superflowSiteUrl,
                    window.superflowPluginOptions.superflow_connection_email,
                );
            } catch (e) {}
        }

        const disconnectInfo = {
            apiKey: "",
            projectId: "",
            id: "",
            state: "pending",
            serverDocId: ""
        };
        window.superflowPluginOptions = {
            ...window.superflowPluginOptions,
            superflow_api_key: "",
            superflow_project_id: "",
            superflow_connection_id: "",
            superflow_connection_status: "",
            superflow_server_doc_id: "",
        };
        await updateSettings(disconnectInfo);
        $disconnectBtn.attr("disabled", false);
        toggleView(false);
    });

    toggleView(window?.superflowPluginOptions?.superflow_connection_status === 'created');
});
