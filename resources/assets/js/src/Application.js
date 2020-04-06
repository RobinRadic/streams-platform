import Vue from 'vue';
import {Container} from 'inversify';
//import {merge} from 'lodash';
//import Axios from 'axios';
import {Dispatcher} from './Dispatcher';
import {ServiceProvider} from './ServiceProvider';
//import {Config} from './Config';

import vuetify from '../plugins/vuetify'

Vue.use(vuetify);

const getConfigDefaults = () => ({
    debug     : false,
    csrf      : null,
    delimiters: [ '\{\{', '}}' ]
})

export class Application extends Container {

    constructor() {
        super({
            // What are these?
            autoBindInjectable : false,
            defaultScope       : 'Transient',
            skipBaseClassChecks: false
        });

        this.root = Vue.extend({});

        this.loadedProviders = {};
        this.providers = [];

        this.booted = false;
        this.started = false;

        this.shuttingDown = false;
        this.startEnabled = true;

        //this.instance('app', this);
        //this.singleton('events', Dispatcher);
        this.events = new Dispatcher;
    }

    // extendRoot(options) {
    //     this.root = this.root.extend(options);
    //     return this;
    // }

    /** @return {Storage} */
    // get storage() {
    //     return this.get('storage');
    // }

    /** @return {Cookies} */
    // get cookies() {
    //     return this.get('cookies');
    // }

    /** @return {Dispatcher} */
    // get events() {
    //     return this.get('events');
    // }

    /** @return {Config} */
    // get data() {
    //     return this.get('data');
    // }

    // async bootstrap(_options, ...mergeOptions) {
    //     let options = merge({
    //         providers: [],
    //         config   : {},
    //         data     : {}
    //     }, _options, ...mergeOptions);
    //     log('bootstrap', {options});
    //     this.events.emit('app:bootstrap', options); //this.hooks.bootstrap.call(options);

    //     this.instance('data', Config.proxied(options.data));
    //     this.addBindingGetter('data');

    //     await this.loadProviders(options.providers);
    //     this.configure(options.config);

    //     await this.registerProviders(this.providers);
    //     this.events.emit('app:bootstrapped', options);
    //     return this;
    // }

    /**
     * Load an array of service providers.
     * 
     * @param {*} Providers 
     */
    async loadProviders(Providers) {
        
        await Promise.all(Providers.map(async Provider => this.loadProvider(Provider)));

        return this;
    }

    /**
     * Load a service provider.
     * 
     * @param {*} Provider 
     */
    async loadProvider(Provider) {
        
        if (Provider.name in this.loadedProviders) {
            return this.loadedProviders[Provider.name];
        }
        
        let provider = new Provider(this);

        if ('configure' in provider && Reflect.getMetadata('configure', provider) !== true) {
            const defaults = getConfigDefaults();
            Reflect.defineMetadata('configure', true, provider);
            await provider.configure(defaults);
        }
        
        if ('providers' in provider && Reflect.getMetadata('providers', provider) !== true) {
            Reflect.defineMetadata('providers', true, provider);
            await this.loadProviders(provider.providers);
        }
        
        this.loadedProviders[Provider.name] = provider;
        
        this.providers.push(provider);
                
        return provider;
    }

    // configure(config) {
    //     config = merge({}, getConfigDefaults, config);
    //     this.events.emit('app:configure', config);
    //     let instance = Config.proxied(config);
    //     this.instance('config', instance);
    //     this.events.emit('app:configured', instance);
    //     return this;
    // }

    /**
     * Register an array of providers.
     * 
     * @param {*} providers 
     */
    async registerProviders(providers) {
        
        await Promise.all(providers.map(async Provider => this.register(Provider)));
        
        return this;
    }

    async register(Provider) {
      
        let provider = Provider;

        if (Provider instanceof ServiceProvider === false) {
            provider = await this.loadProvider(Provider);
        }

        if ('register' in provider && Reflect.getMetadata('register', provider) !== true) {
            
            Reflect.defineMetadata('register', true, provider);
            
            await this.loadAsync(new AsyncContainerModule(() => provider.register()));
        }

        this.providers.push(provider);
  
        return this;
    }

    async boot() {
        
        /**
         * Only boot once.
         */
        if (this.booted) {
            return this;
        }

        this.booted = true;
        
        this.events.emit('app:boot');

        for (const provider of this.providers) {
            
            if ('boot' in provider && Reflect.getMetadata('boot', provider) !== true ) {
                
                Reflect.defineMetadata('boot', true, provider);
                
                await provider.boot();
            }
        }

        this.events.emit('app:booted');

        return this;
    }

    async start(selector){
        
        this.root = new Vue({
            vuetify
        });

        this.root.$mount(selector);

        return this;
    }

    // start = async (elementOrSelector='#app') => {
    //     log('start',{elementOrSelector,data:this.data,Root:this.root})
    //     this.events.emit('app:start', elementOrSelector, {})
    //     this.root = new this.root({
    //         data: () => {
    //             return this.data
    //         }

    //     });
    //     this.root.$mount(elementOrSelector);
    //     this.events.emit('app:started')
    //     log('started', this.root)
    // }
    
    // error = async (error) => {
    //     log('error', { error });
    //     this.events.emit('app:error',error)
    //     throw error;
    // };

    // addBindingGetter(id, key = null) {
    //     key        = key || id;
    //     const self = this;
    //     Object.defineProperty(this, key, {
    //         get() {return self.get(id);}
    //     });
    // }
    
    //region: ioc
    // alias(abstract, alias, singleton = false) {
    //     let binding = this.bind(alias).toDynamicValue(ctx => ctx.container.get(abstract));
    //     if ( singleton ) {
    //         binding.inSingletonScope();
    //     }
    //     return this;
    // }

    // bindIf(id, override, cb) {
    //     if ( this.isBound(id) && !override ) return this;
    //     cb(this.isBound(id) ? this.rebind(id) : this.bind(id));
    //     return this;
    // }

    // dynamic(id, cb) {
    //     return this.bind(id).toDynamicValue(ctx => {
    //         let req = ctx.currentRequest;
    //         return cb(this);
    //     });
    // }

    // singleton(id, value, override = false) {
    //     return this.bindIf(id, override, b => b.to(value).inSingletonScope());
    // }

    // binding(id, value) {

    //     return this;
    // }

    // instance(id, value, override = false) {
    //     return this.bindIf(id, override, b => b.toConstantValue(value));
    // }

    // ctxfactory(id, factory) {
    //     this.bind(id).toFactory(ctx => factory(ctx));
    //     return this;
    // }

    // factory(id, factory) {
    //     this.bind(id).toFactory(ctx => factory);
    //     return this;
    // }

    //endregion
}
